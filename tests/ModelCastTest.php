<?php

declare(strict_types=1);

namespace Vortex\Tests;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Vortex\AppContext;
use Vortex\Config\Repository;
use Vortex\Container;
use Vortex\Database\Connection;
use Vortex\Database\DatabaseManager;
use Vortex\Database\Model;

final class ModelCastTest extends TestCase
{
    private string $configDir = '';

    protected function setUp(): void
    {
        parent::setUp();
        Model::forgetRegisteredObservers();

        $this->configDir = sys_get_temp_dir() . '/vortex-model-cast-' . bin2hex(random_bytes(4));
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

        CastThing::connection()->execute(
            'CREATE TABLE cast_things (id INTEGER PRIMARY KEY AUTOINCREMENT, flag INTEGER NOT NULL DEFAULT 0, payload TEXT, amount REAL, label TEXT, starts_at TEXT)',
        );
    }

    protected function tearDown(): void
    {
        Model::forgetRegisteredObservers();
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

    public function testFindCastsJsonAndBoolFromDatabase(): void
    {
        CastThing::connection()->execute(
            'INSERT INTO cast_things (flag, payload, amount, label, starts_at) VALUES (?, ?, ?, ?, ?)',
            [0, '{"a":1}', 3.5, 'x', '2026-01-02 15:30:00'],
        );
        $row = CastThing::find(1);
        self::assertInstanceOf(CastThing::class, $row);
        self::assertFalse((bool) ($row->flag ?? true));
        self::assertSame(['a' => 1], $row->payload);
        self::assertSame(3.5, $row->amount);
        self::assertSame('x', $row->label);
        self::assertInstanceOf(DateTimeImmutable::class, $row->starts_at);
        self::assertSame('2026-01-02 15:30:00', $row->starts_at->format('Y-m-d H:i:s'));
    }

    public function testSaveEncodesJsonAndBool(): void
    {
        $m = CastThing::create([
            'flag' => true,
            'payload' => ['z' => 9],
            'amount' => 2.25,
            'label' => 'hi',
            'starts_at' => new DateTimeImmutable('2026-03-04 10:00:00'),
        ]);

        $raw = CastThing::connection()->selectOne('SELECT flag, payload, amount, starts_at FROM cast_things WHERE id = ?', [(int) $m->id]);
        self::assertNotNull($raw);
        self::assertSame(1, (int) $raw['flag']);
        self::assertSame('{"z":9}', (string) $raw['payload']);
        self::assertEqualsWithDelta(2.25, (float) $raw['amount'], 0.001);
        self::assertSame('2026-03-04 10:00:00', (string) $raw['starts_at']);
    }

    public function testUpdatePersistsCasts(): void
    {
        $m = CastThing::create([
            'flag' => false,
            'payload' => [],
            'amount' => 1.0,
            'label' => 'a',
            'starts_at' => new DateTimeImmutable('2026-01-01 00:00:00'),
        ]);
        $m->flag = true;
        $m->payload = ['k' => 'v'];
        $m->save();

        $again = CastThing::find((int) $m->id);
        self::assertNotNull($again);
        self::assertTrue((bool) $again->flag);
        self::assertSame(['k' => 'v'], $again->payload);
    }

    public function testUnknownCastThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        BadCastThing::connection()->execute(
            'CREATE TABLE bad_cast_things (id INTEGER PRIMARY KEY, weird TEXT)',
        );
        BadCastThing::fromRow(['id' => 1, 'weird' => 'x']);
    }
}

final class CastThing extends Model
{
    protected static ?string $table = 'cast_things';

    /** @var list<string> */
    protected static array $fillable = ['flag', 'payload', 'amount', 'label', 'starts_at'];

    /** @var array<string, string> */
    protected static array $casts = [
        'flag' => 'bool',
        'payload' => 'json',
        'amount' => 'float',
        'label' => 'string',
        'starts_at' => 'datetime',
    ];

    protected static bool $timestamps = false;
}

final class BadCastThing extends Model
{
    protected static ?string $table = 'bad_cast_things';

    /** @var list<string> */
    protected static array $fillable = ['weird'];

    /** @var array<string, string> */
    protected static array $casts = ['weird' => 'nope'];

    protected static bool $timestamps = false;
}
