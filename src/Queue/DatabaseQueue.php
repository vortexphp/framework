<?php

declare(strict_types=1);

namespace Vortex\Queue;

use InvalidArgumentException;
use Vortex\Database\Connection;
use Vortex\Queue\Contracts\Job;

/**
 * SQL-backed FIFO queue: jobs are PHP-serialized {@see Job} instances.
 *
 * Expected table (see {@see README.md} in this directory): {@code queue}, {@code payload}, {@code attempts},
 * {@code reserved_at}, {@code available_at}, {@code created_at}.
 */
final class DatabaseQueue
{
    private readonly string $tableSql;

    public function __construct(
        private readonly Connection $db,
        string $table = 'jobs',
    ) {
        if ($table === '' || ! preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            throw new InvalidArgumentException('Queue table name must be a non-empty alphanumeric / underscore identifier.');
        }
        $this->tableSql = $table;
    }

    public function push(string $queue, Job $job, int $delaySeconds = 0): void
    {
        $payload = serialize($job);
        $now = time();
        $availableAt = $now + max(0, $delaySeconds);
        $this->db->execute(
            "INSERT INTO {$this->tableSql} (queue, payload, attempts, reserved_at, available_at, created_at) VALUES (?, ?, 0, NULL, ?, ?)",
            [$queue, $payload, $availableAt, $now],
        );
    }

    /**
     * Claims the next due job for this queue, or null if none.
     *
     * @param positive-int $staleReserveSeconds reservations older than this many seconds are treated as abandoned and reclaimed
     */
    public function reserve(string $queue, int $staleReserveSeconds): ?ReservedJob
    {
        $staleReserveSeconds = max(1, $staleReserveSeconds);
        $reclaimBefore = time() - $staleReserveSeconds;

        return $this->db->transaction(function (Connection $db) use ($queue, $reclaimBefore): ?ReservedJob {
            $now = time();
            $row = $db->selectOne(
                "SELECT id, payload, attempts FROM {$this->tableSql} WHERE queue = ? AND available_at <= ? AND (reserved_at IS NULL OR reserved_at < ?) ORDER BY id ASC LIMIT 1",
                [$queue, $now, $reclaimBefore],
            );
            if ($row === null) {
                return null;
            }

            $id = (int) $row['id'];
            $updated = $db->execute(
                "UPDATE {$this->tableSql} SET reserved_at = ? WHERE id = ? AND (reserved_at IS NULL OR reserved_at < ?)",
                [$now, $id, $reclaimBefore],
            );
            if ($updated < 1) {
                return null;
            }

            return new ReservedJob($id, (string) $row['payload'], (int) $row['attempts']);
        });
    }

    /**
     * @param positive-int $id
     */
    public function delete(int $id): void
    {
        $this->db->execute("DELETE FROM {$this->tableSql} WHERE id = ?", [$id]);
    }

    /**
     * @param positive-int         $id
     * @param non-negative-int     $attempts      new attempts count after failure
     * @param non-negative-int     $delaySeconds  delay before the job is due again
     */
    public function release(int $id, int $attempts, int $delaySeconds): void
    {
        $availableAt = time() + max(0, $delaySeconds);
        $this->db->execute(
            "UPDATE {$this->tableSql} SET reserved_at = NULL, attempts = ?, available_at = ? WHERE id = ?",
            [$attempts, $availableAt, $id],
        );
    }
}
