<?php

declare(strict_types=1);

namespace Vortex\Tests;

use LogicException;
use PHPUnit\Framework\TestCase;
use Vortex\AppContext;
use Vortex\Config\Repository;
use Vortex\Container;
use Vortex\Database\Connection;
use Vortex\Database\DatabaseManager;
use Vortex\Database\Model;

final class ModelPersistenceHelpersTest extends TestCase
{
    private string $configDir = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->configDir = sys_get_temp_dir() . '/vortex-model-persist-' . bin2hex(random_bytes(4));
        mkdir($this->configDir, 0700, true);
        file_put_contents(
            $this->configDir . '/database.php',
            <<<'PHP'
<?php
return [
    'default' => 'default',
    'connections' => [
        'default' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'host' => '127.0.0.1',
            'port' => '3306',
            'username' => '',
            'password' => '',
        ],
    ],
];
PHP
        );
        Repository::setInstance(new Repository($this->configDir));

        $container = new Container();
        $container->instance(Container::class, $container);
        $container->singleton(DatabaseManager::class, static fn (): DatabaseManager => DatabaseManager::fromRepository());
        $container->singleton(Connection::class, static fn (Container $c): Connection => $c->make(DatabaseManager::class)->connection());
        AppContext::set($container);

        PersistKitten::connection()->execute(
            'CREATE TABLE persist_kittens (id INTEGER PRIMARY KEY AUTOINCREMENT, code TEXT NOT NULL UNIQUE, name TEXT NOT NULL)',
        );
    }

    protected function tearDown(): void
    {
        $ref = new \ReflectionClass(AppContext::class);
        $prop = $ref->getProperty('container');
        $prop->setAccessible(true);
        $prop->setValue(null, null);
        Repository::forgetInstance();
        if ($this->configDir !== '' && is_file($this->configDir . '/database.php')) {
            unlink($this->configDir . '/database.php');
            rmdir($this->configDir);
        }
        parent::tearDown();
    }

    public function testRefreshReloadsFromDatabase(): void
    {
        $m = PersistKitten::create(['code' => 'a', 'name' => 'Ada']);
        PersistKitten::connection()->execute(
            'UPDATE persist_kittens SET name = ? WHERE id = ?',
            ['Ava', (int) $m->id],
        );
        self::assertSame('Ada', (string) $m->name);
        $m->refresh();
        self::assertSame('Ava', (string) $m->name);
    }

    public function testRefreshWithoutIdThrows(): void
    {
        $m = new PersistKitten();
        $this->expectException(LogicException::class);
        $m->refresh();
    }

    public function testFirstOrCreateReturnsExistingOrInserts(): void
    {
        $a = PersistKitten::firstOrCreate(['code' => 'x'], ['name' => 'first name']);
        self::assertSame('first name', (string) $a->name);
        $b = PersistKitten::firstOrCreate(['code' => 'x'], ['name' => 'ignored']);
        self::assertSame((int) $a->id, (int) $b->id);
        self::assertSame('first name', (string) $b->name);
    }

    public function testUpdateOrCreateUpdatesOrInserts(): void
    {
        $u = PersistKitten::updateOrCreate(['code' => 'u'], ['name' => 'one']);
        self::assertSame('one', (string) $u->name);
        $u2 = PersistKitten::updateOrCreate(['code' => 'u'], ['name' => 'two']);
        self::assertSame((int) $u->id, (int) $u2->id);
        self::assertSame('two', (string) $u2->name);

        $n = PersistKitten::updateOrCreate(['code' => 'new'], ['name' => 'n']);
        self::assertSame('new', (string) $n->code);
        self::assertSame('n', (string) $n->name);
    }
}

/** @internal */
final class PersistKitten extends Model
{
    protected static ?string $table = 'persist_kittens';

    /** @var list<string> */
    protected static array $fillable = ['code', 'name'];

    protected static bool $timestamps = false;
}
