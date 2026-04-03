<?php

declare(strict_types=1);

namespace Vortex\Tests;

use PHPUnit\Framework\TestCase;
use Vortex\AppContext;
use Vortex\Config\Repository;
use Vortex\Container;
use Vortex\Database\Connection;
use Vortex\Database\DB;
use RuntimeException;

final class DBFacadeTest extends TestCase
{
    private string $configDir = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->configDir = sys_get_temp_dir() . '/pc-db-facade-' . bin2hex(random_bytes(4));
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

        $container = new Container();
        $container->instance(Container::class, $container);
        $container->singleton(Connection::class, static fn (): Connection => new Connection());
        AppContext::set($container);
    }

    protected function tearDown(): void
    {
        $this->clearAppContext();
        Repository::forgetInstance();
        if ($this->configDir !== '' && is_file($this->configDir . '/database.php')) {
            unlink($this->configDir . '/database.php');
            rmdir($this->configDir);
        }
        parent::tearDown();
    }

    private function clearAppContext(): void
    {
        $ref = new \ReflectionClass(AppContext::class);
        $prop = $ref->getProperty('container');
        $prop->setAccessible(true);
        $prop->setValue(null, null);
    }

    public function testTransactionCommits(): void
    {
        DB::execute('CREATE TABLE t (id INTEGER PRIMARY KEY, v TEXT)');
        DB::transaction(static function (Connection $db): void {
            $db->execute('INSERT INTO t (v) VALUES (?)', ['ok']);
        });
        self::assertSame([['v' => 'ok']], DB::select('SELECT v FROM t'));
        self::assertFalse(DB::inTransaction());
    }

    public function testTransactionRollsBackOnException(): void
    {
        DB::execute('CREATE TABLE t (id INTEGER PRIMARY KEY, v TEXT)');
        DB::execute('INSERT INTO t (v) VALUES (?)', ['before']);

        try {
            DB::transaction(static function (Connection $db): void {
                $db->execute('INSERT INTO t (v) VALUES (?)', ['during']);
                throw new RuntimeException('fail');
            });
            self::fail('Expected exception');
        } catch (RuntimeException $e) {
            self::assertSame('fail', $e->getMessage());
        }

        self::assertSame([['v' => 'before']], DB::select('SELECT v FROM t ORDER BY id'));
        self::assertFalse(DB::inTransaction());
    }

    public function testDelegatesMatchConnectionInstance(): void
    {
        $fromContext = AppContext::container()->make(Connection::class);
        DB::execute('CREATE TABLE u (id INTEGER PRIMARY KEY)');
        $fromContext->execute('INSERT INTO u DEFAULT VALUES');

        self::assertSame(1, (int) (DB::selectOne('SELECT id FROM u LIMIT 1')['id'] ?? 0));
    }
}
