<?php

declare(strict_types=1);

namespace Vortex\Tests;

use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;
use Vortex\Config\Repository;
use Vortex\Database\Connection;
use Vortex\Database\DatabaseManager;

final class DatabaseManagerTest extends TestCase
{
    private string $configDir = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->configDir = sys_get_temp_dir() . '/vortex -db-mgr-' . bin2hex(random_bytes(4));
        mkdir($this->configDir, 0700, true);
    }

    protected function tearDown(): void
    {
        Repository::forgetInstance();
        if ($this->configDir !== '' && is_dir($this->configDir)) {
            foreach (glob($this->configDir . '/*.php') ?: [] as $f) {
                unlink($f);
            }
            rmdir($this->configDir);
        }
        parent::tearDown();
    }

    public function testNamedConnectionsAreDistinctPdos(): void
    {
        file_put_contents(
            $this->configDir . '/database.php',
            <<<'PHP'
<?php
return [
    'default' => 'a',
    'connections' => [
        'a' => ['driver' => 'sqlite', 'database' => ':memory:', 'host' => '', 'port' => '', 'username' => '', 'password' => ''],
        'b' => ['driver' => 'sqlite', 'database' => ':memory:', 'host' => '', 'port' => '', 'username' => '', 'password' => ''],
    ],
];
PHP
        );
        Repository::setInstance(new Repository($this->configDir));
        $mgr = DatabaseManager::fromRepository();
        $a = $mgr->connection('a');
        $b = $mgr->connection('b');
        self::assertNotSame($a->pdo(), $b->pdo());
        $a->execute('CREATE TABLE t (id INTEGER PRIMARY KEY)');
        $b->execute('CREATE TABLE u (id INTEGER PRIMARY KEY)');
        self::assertSame([], $b->select('SELECT name FROM sqlite_master WHERE type=\'table\' AND name=\'t\''));
    }

    public function testUnknownDriverThrows(): void
    {
        $mgr = DatabaseManager::fromConfig([
            'default' => 'x',
            'connections' => [
                'x' => ['driver' => 'oracle', 'database' => 'x', 'host' => 'h', 'port' => '1', 'username' => '', 'password' => ''],
            ],
        ]);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown database driver');
        $mgr->connection('x');
    }

    public function testMissingConnectionsConfigThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No database connections configured.');
        DatabaseManager::fromConfig([
            'driver' => 'sqlite',
        ]);
    }

    public function testFromInstances(): void
    {
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $conn = new Connection($pdo);
        $mgr = DatabaseManager::fromInstances('main', ['main' => $conn]);
        self::assertSame($conn, $mgr->connection());
        self::assertSame($conn, $mgr->connection('main'));
    }
}
