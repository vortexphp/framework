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

final class ModelSoftDeleteTest extends TestCase
{
    private string $configDir = '';

    protected function setUp(): void
    {
        parent::setUp();
        Model::forgetRegisteredObservers();

        $this->configDir = sys_get_temp_dir() . '/vortex-soft-' . bin2hex(random_bytes(4));
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

        SoftPost::connection()->execute(
            'CREATE TABLE soft_posts (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, deleted_at TEXT NULL)',
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

    public function testFindIgnoresSoftDeleted(): void
    {
        $p = SoftPost::create(['title' => 'a']);
        SoftPost::connection()->execute(
            'UPDATE soft_posts SET deleted_at = ? WHERE id = ?',
            ['2026-01-01 00:00:00', (int) $p->id],
        );

        self::assertNull(SoftPost::find((int) $p->id));
        $hit = SoftPost::query()->withTrashed()->where('id', (int) $p->id)->first();
        self::assertNotNull($hit);
        self::assertSame('a', (string) $hit->title);
    }

    public function testDeleteSetsDeletedAtAndKeepsId(): void
    {
        $p = SoftPost::create(['title' => 'b']);
        $id = (int) $p->id;
        $p->delete();

        self::assertNull(SoftPost::find($id));
        self::assertSame($id, (int) $p->id);
        self::assertNotNull($p->deleted_at);

        $raw = SoftPost::connection()->selectOne('SELECT deleted_at FROM soft_posts WHERE id = ?', [$id]);
        self::assertNotNull($raw['deleted_at'] ?? null);
    }

    public function testRestoreClearsDeletedAt(): void
    {
        $p = SoftPost::create(['title' => 'c']);
        $id = (int) $p->id;
        $p->delete();
        $p->restore();

        self::assertNull($p->deleted_at);
        $again = SoftPost::find($id);
        self::assertNotNull($again);
        self::assertSame('c', (string) $again->title);
    }

    public function testRestoreThrowsWhenSoftDeletesOff(): void
    {
        $this->expectException(LogicException::class);
        (new HardPost())->restore();
    }

    public function testForceDeleteRemovesRow(): void
    {
        $p = SoftPost::create(['title' => 'd']);
        $id = (int) $p->id;
        $p->forceDelete();

        $raw = SoftPost::connection()->selectOne('SELECT id FROM soft_posts WHERE id = ?', [$id]);
        self::assertNull($raw);
        self::assertNull($p->id);
    }

    public function testQueryDeleteSoftDeletesMatchingRows(): void
    {
        SoftPost::create(['title' => 'e1']);
        SoftPost::create(['title' => 'e2']);
        SoftPost::query()->where('title', 'e1')->delete();

        self::assertSame(1, SoftPost::query()->count());
        self::assertSame(2, SoftPost::query()->withTrashed()->count());
    }

    public function testOnlyTrashedDeleteHardDeletes(): void
    {
        $p = SoftPost::create(['title' => 'f']);
        $p->delete();
        SoftPost::query()->onlyTrashed()->delete();

        $raw = SoftPost::connection()->selectOne('SELECT id FROM soft_posts WHERE id = ?', [(int) $p->id]);
        self::assertNull($raw);
    }
}

final class SoftPost extends Model
{
    protected static ?string $table = 'soft_posts';

    /** @var list<string> */
    protected static array $fillable = ['title', 'deleted_at'];

    protected static bool $timestamps = false;

    protected static bool $softDeletes = true;
}

final class HardPost extends Model
{
    protected static ?string $table = 'soft_posts';

    /** @var list<string> */
    protected static array $fillable = ['title'];

    protected static bool $timestamps = false;

    protected static bool $softDeletes = false;
}
