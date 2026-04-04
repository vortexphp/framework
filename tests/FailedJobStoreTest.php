<?php

declare(strict_types=1);

namespace Vortex\Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Vortex\Database\Connection;
use Vortex\Queue\FailedJobStore;

final class FailedJobStoreTest extends TestCase
{
    private Connection $db;

    private FailedJobStore $store;

    protected function setUp(): void
    {
        parent::setUp();
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $pdo->exec('CREATE TABLE failed_jobs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            queue VARCHAR(255) NOT NULL,
            payload TEXT NOT NULL,
            exception TEXT NOT NULL,
            failed_at INTEGER NOT NULL
        )');
        $this->db = new Connection($pdo);
        $this->store = new FailedJobStore($this->db, 'failed_jobs');
    }

    public function testEmptyTableNameDisablesRecording(): void
    {
        $silent = new FailedJobStore($this->db, '');
        self::assertFalse($silent->isRecording());
        $silent->record('q', 'x', new RuntimeException('x'));
        $row = $this->db->selectOne('SELECT COUNT(*) AS c FROM failed_jobs', []);
        self::assertSame(0, (int) $row['c']);
    }

    public function testRecordAndRecent(): void
    {
        $this->store->record('default', 'payload', new RuntimeException('boom'));
        $rows = $this->store->recent(10);
        self::assertCount(1, $rows);
        self::assertSame('default', $rows[0]['queue']);
        self::assertStringContainsString('boom', $rows[0]['exception']);
    }

    public function testFindDelete(): void
    {
        $this->store->record('emails', 'p', new RuntimeException('e'));
        $id = (int) $this->db->selectOne('SELECT id FROM failed_jobs', [])['id'];
        $row = $this->store->find($id);
        self::assertSame('emails', $row['queue']);
        self::assertSame('p', $row['payload']);
        $this->store->delete($id);
        self::assertNull($this->store->find($id));
    }
}
