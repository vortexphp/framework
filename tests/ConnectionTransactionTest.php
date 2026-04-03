<?php

declare(strict_types=1);

namespace Vortex\Tests;

use PHPUnit\Framework\TestCase;
use Vortex\Config\Repository;
use Vortex\Database\Connection;
use RuntimeException;

final class ConnectionTransactionTest extends TestCase
{
    private string $configDir = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->configDir = sys_get_temp_dir() . '/pc-conn-tx-' . bin2hex(random_bytes(4));
        mkdir($this->configDir, 0700, true);
        file_put_contents(
            $this->configDir . '/database.php',
            <<<'PHP'
<?php
return [
    'driver' => 'sqlite',
    'database' => ':memory:',
    'host' => '127.0.0.1',
    'port' => '3306',
    'username' => '',
    'password' => '',
];
PHP
            ,
        );
        Repository::setInstance(new Repository($this->configDir));
    }

    protected function tearDown(): void
    {
        Repository::forgetInstance();
        if ($this->configDir !== '' && is_file($this->configDir . '/database.php')) {
            unlink($this->configDir . '/database.php');
            rmdir($this->configDir);
        }
        parent::tearDown();
    }

    public function testTransactionCommits(): void
    {
        $c = new Connection();
        $c->execute('CREATE TABLE t (id INTEGER PRIMARY KEY, v TEXT)');
        $c->transaction(static function (Connection $db): void {
            $db->execute('INSERT INTO t (v) VALUES (?)', ['ok']);
        });
        $rows = $c->select('SELECT v FROM t');
        self::assertSame([['v' => 'ok']], $rows);
        self::assertFalse($c->inTransaction());
    }

    public function testTransactionRollsBackOnException(): void
    {
        $c = new Connection();
        $c->execute('CREATE TABLE t (id INTEGER PRIMARY KEY, v TEXT)');
        $c->execute('INSERT INTO t (v) VALUES (?)', ['before']);

        try {
            $c->transaction(static function (Connection $db): void {
                $db->execute('INSERT INTO t (v) VALUES (?)', ['during']);
                throw new RuntimeException('fail');
            });
            self::fail('Expected exception');
        } catch (RuntimeException $e) {
            self::assertSame('fail', $e->getMessage());
        }

        $rows = $c->select('SELECT v FROM t ORDER BY id');
        self::assertSame([['v' => 'before']], $rows);
        self::assertFalse($c->inTransaction());
    }

    public function testManualBeginCommit(): void
    {
        $c = new Connection();
        $c->execute('CREATE TABLE t (id INTEGER PRIMARY KEY, v TEXT)');
        $c->beginTransaction();
        $c->execute('INSERT INTO t (v) VALUES (?)', ['x']);
        self::assertTrue($c->inTransaction());
        $c->commit();
        self::assertFalse($c->inTransaction());
        self::assertSame(1, count($c->select('SELECT * FROM t')));
    }

    public function testManualRollBack(): void
    {
        $c = new Connection();
        $c->execute('CREATE TABLE t (id INTEGER PRIMARY KEY, v TEXT)');
        $c->beginTransaction();
        $c->execute('INSERT INTO t (v) VALUES (?)', ['x']);
        $c->rollBack();
        self::assertFalse($c->inTransaction());
        self::assertSame([], $c->select('SELECT * FROM t'));
    }
}
