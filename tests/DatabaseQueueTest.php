<?php

declare(strict_types=1);

namespace Vortex\Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use Vortex\Queue\Contracts\Job;
use Vortex\Queue\DatabaseQueue;
use Vortex\Queue\ReservedJob;
use Vortex\Database\Connection;

final class DatabaseQueueTest extends TestCase
{
    private Connection $db;

    private DatabaseQueue $queue;

    protected function setUp(): void
    {
        parent::setUp();
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $pdo->exec('CREATE TABLE jobs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            queue VARCHAR(255) NOT NULL,
            payload TEXT NOT NULL,
            attempts INTEGER NOT NULL DEFAULT 0,
            reserved_at INTEGER NULL,
            available_at INTEGER NOT NULL,
            created_at INTEGER NOT NULL
        )');
        $this->db = new Connection($pdo);
        $this->queue = new DatabaseQueue($this->db, 'jobs');
    }

    public function testPushReserveDelete(): void
    {
        $job = new DatabaseQueueCountingJob(7);
        $this->queue->push('default', $job);
        self::assertSame(0, DatabaseQueueCountingJob::$total);

        $reserved = $this->queue->reserve('default', 300);
        self::assertInstanceOf(ReservedJob::class, $reserved);

        $un = unserialize($reserved->payload, ['allowed_classes' => true]);
        self::assertInstanceOf(DatabaseQueueCountingJob::class, $un);
        $un->handle();

        self::assertSame(7, DatabaseQueueCountingJob::$total);
        $this->queue->delete($reserved->id);

        self::assertNull($this->queue->reserve('default', 300));
    }

    public function testReserveSkipsFutureAvailableAt(): void
    {
        $this->queue->push('emails', new DatabaseQueueCountingJob(1), delaySeconds: 3600);
        self::assertNull($this->queue->reserve('emails', 300));
    }

    public function testReleaseAndRetry(): void
    {
        $this->queue->push('default', new DatabaseQueueCountingJob(1));

        $first = $this->queue->reserve('default', 300);
        self::assertNotNull($first);
        $this->queue->release($first->id, 1, 0);

        $second = $this->queue->reserve('default', 300);
        self::assertNotNull($second);
        self::assertSame(1, $second->attempts);
    }

    public function testStaleReservationIsReclaimed(): void
    {
        $this->queue->push('default', new DatabaseQueueCountingJob(3));
        $a = $this->queue->reserve('default', 300);
        self::assertNotNull($a);
        $this->db->execute('UPDATE jobs SET reserved_at = ? WHERE id = ?', [1, $a->id]);

        $b = $this->queue->reserve('default', 10);
        self::assertNotNull($b);
        self::assertSame($a->id, $b->id);
    }
}

final class DatabaseQueueCountingJob implements Job
{
    public static int $total = 0;

    public function __construct(
        private readonly int $delta,
    ) {
    }

    public function handle(): void
    {
        self::$total += $this->delta;
    }
}
