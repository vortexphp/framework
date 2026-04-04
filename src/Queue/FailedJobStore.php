<?php

declare(strict_types=1);

namespace Vortex\Queue;

use InvalidArgumentException;
use Throwable;
use Vortex\Database\Connection;

/**
 * Persists permanently failed queue payloads for inspection and {@see DatabaseQueue::pushSerialized()} replay.
 *
 * Set {@code queue.failed_jobs_table} to an empty string to disable {@see record()} (listing / retry still expect the table if you use those commands).
 */
final class FailedJobStore
{
    private readonly string $tableSql;

    public function __construct(
        private readonly Connection $db,
        string $table = 'failed_jobs',
    ) {
        if ($table !== '' && ! preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            throw new InvalidArgumentException('Failed jobs table name must be alphanumeric / underscore or empty to disable recording.');
        }
        $this->tableSql = $table;
    }

    public function isRecording(): bool
    {
        return $this->tableSql !== '';
    }

    public function record(string $queue, string $payload, Throwable $e): void
    {
        if (! $this->isRecording()) {
            return;
        }

        $text = $e->getMessage() . "\n\n" . $e->getTraceAsString();
        if (strlen($text) > 65535) {
            $text = substr($text, 0, 65532) . '...';
        }

        $this->db->execute(
            "INSERT INTO {$this->tableSql} (queue, payload, exception, failed_at) VALUES (?, ?, ?, ?)",
            [$queue, $payload, $text, time()],
        );
    }

    /**
     * @return list<array{id: int, queue: string, exception: string, failed_at: int}>
     */
    public function recent(int $limit): array
    {
        if (! $this->isRecording()) {
            return [];
        }

        $limit = max(1, min(500, $limit));
        $rows = $this->db->select(
            "SELECT id, queue, exception, failed_at FROM {$this->tableSql} ORDER BY id DESC LIMIT ?",
            [$limit],
        );

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id' => (int) $row['id'],
                'queue' => (string) $row['queue'],
                'exception' => (string) $row['exception'],
                'failed_at' => (int) $row['failed_at'],
            ];
        }

        return $out;
    }

    /**
     * @return null|array{id: int, queue: string, payload: string, exception: string, failed_at: int}
     */
    public function find(int $id): ?array
    {
        if (! $this->isRecording()) {
            return null;
        }

        $row = $this->db->selectOne(
            "SELECT id, queue, payload, exception, failed_at FROM {$this->tableSql} WHERE id = ?",
            [$id],
        );
        if ($row === null) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'queue' => (string) $row['queue'],
            'payload' => (string) $row['payload'],
            'exception' => (string) $row['exception'],
            'failed_at' => (int) $row['failed_at'],
        ];
    }

    /**
     * @return list<array{id: int, queue: string, payload: string}>
     */
    public function allForRetry(): array
    {
        if (! $this->isRecording()) {
            return [];
        }

        $rows = $this->db->select(
            "SELECT id, queue, payload FROM {$this->tableSql} ORDER BY id ASC",
            [],
        );
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id' => (int) $row['id'],
                'queue' => (string) $row['queue'],
                'payload' => (string) $row['payload'],
            ];
        }

        return $out;
    }

    public function delete(int $id): void
    {
        if (! $this->isRecording()) {
            return;
        }

        $this->db->execute("DELETE FROM {$this->tableSql} WHERE id = ?", [$id]);
    }
}
