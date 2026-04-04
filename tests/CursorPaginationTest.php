<?php

declare(strict_types=1);

namespace Vortex\Tests;

use PHPUnit\Framework\TestCase;
use Vortex\AppContext;
use Vortex\Config\Repository;
use Vortex\Container;
use Vortex\Database\Connection;
use Vortex\Database\DatabaseManager;
use Vortex\Database\Model;
use Vortex\Pagination\Cursor;
use Vortex\Pagination\CursorPaginator;
use Vortex\Pagination\InvalidCursorException;

final class CursorPaginationTest extends TestCase
{
    private string $configDir = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->configDir = sys_get_temp_dir() . '/vortex-cursor-pag-' . bin2hex(random_bytes(4));
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
            ,
        );
        Repository::setInstance(new Repository($this->configDir));

        $container = new Container();
        $container->instance(Container::class, $container);
        $container->singleton(DatabaseManager::class, static fn (): DatabaseManager => DatabaseManager::fromRepository());
        $container->singleton(Connection::class, static fn (Container $c): Connection => $c->make(DatabaseManager::class)->connection());
        AppContext::set($container);

        CursorThing::connection()->execute(
            'CREATE TABLE cursor_things (id INTEGER PRIMARY KEY AUTOINCREMENT, sort_key INTEGER NOT NULL, label TEXT NOT NULL)'
        );
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

    public function testCursorRoundTrip(): void
    {
        $t = Cursor::encode(['id' => 99, 'extra' => true]);
        self::assertSame(['id' => 99, 'extra' => true], Cursor::decode($t));
    }

    public function testCursorRejectsNonObjectJson(): void
    {
        $this->expectException(InvalidCursorException::class);
        Cursor::decode(Cursor::encode([]));
    }

    public function testCursorRejectsListJson(): void
    {
        $t = rtrim(strtr(base64_encode('[1,2]'), '+/', '-_'), '=');
        $this->expectException(InvalidCursorException::class);
        Cursor::decode($t);
    }

    public function testCursorRejectsGarbage(): void
    {
        $this->expectException(InvalidCursorException::class);
        Cursor::decode('not-valid-base64???');
    }

    public function testCursorPaginatorToApiData(): void
    {
        $p = new CursorPaginator([1, 2], 'next-tok', true, 10);
        self::assertSame([
            'items' => [2, 4],
            'next_cursor' => 'next-tok',
            'has_more' => true,
            'per_page' => 10,
        ], $p->toApiData(static fn (int $x): int => $x * 2));
    }

    public function testCursorPaginateAscAcrossPages(): void
    {
        foreach (range(1, 5) as $i) {
            CursorThing::create(['sort_key' => $i, 'label' => 'L' . $i]);
        }
        $p1 = CursorThing::query()->cursorPaginate(null, 2, 'sort_key', 'ASC');
        self::assertCount(2, $p1->items);
        self::assertTrue($p1->has_more);
        self::assertNotNull($p1->next_cursor);
        self::assertSame(1, (int) $p1->items[0]->sort_key);
        self::assertSame(2, (int) $p1->items[1]->sort_key);

        $p2 = CursorThing::query()->cursorPaginate($p1->next_cursor, 2, 'sort_key', 'ASC');
        self::assertCount(2, $p2->items);
        self::assertTrue($p2->has_more);
        self::assertSame(3, (int) $p2->items[0]->sort_key);

        $p3 = CursorThing::query()->cursorPaginate($p2->next_cursor, 2, 'sort_key', 'ASC');
        self::assertCount(1, $p3->items);
        self::assertFalse($p3->has_more);
        self::assertNull($p3->next_cursor);
        self::assertSame(5, (int) $p3->items[0]->sort_key);
    }

    public function testCursorPaginateDesc(): void
    {
        foreach (range(1, 4) as $i) {
            CursorThing::create(['sort_key' => $i, 'label' => 'L' . $i]);
        }
        $p1 = CursorThing::query()->cursorPaginate(null, 2, 'sort_key', 'DESC');
        self::assertSame(4, (int) $p1->items[0]->sort_key);
        self::assertSame(3, (int) $p1->items[1]->sort_key);
        self::assertTrue($p1->has_more);

        $p2 = CursorThing::query()->cursorPaginate($p1->next_cursor, 2, 'sort_key', 'DESC');
        self::assertSame(2, (int) $p2->items[0]->sort_key);
        self::assertSame(1, (int) $p2->items[1]->sort_key);
        self::assertFalse($p2->has_more);
        self::assertNull($p2->next_cursor);
    }

    public function testCursorPaginateMissingColumnInTokenThrows(): void
    {
        CursorThing::create(['sort_key' => 1, 'label' => 'a']);
        $bad = Cursor::encode(['wrong' => 1]);
        $this->expectException(InvalidCursorException::class);
        CursorThing::query()->cursorPaginate($bad, 5, 'sort_key');
    }

    private function clearAppContext(): void
    {
        $ref = new \ReflectionClass(AppContext::class);
        $prop = $ref->getProperty('container');
        $prop->setAccessible(true);
        $prop->setValue(null, null);
    }
}

final class CursorThing extends Model
{
    protected static ?string $table = 'cursor_things';

    /** @var list<string> */
    protected static array $fillable = ['sort_key', 'label'];

    protected static bool $timestamps = false;
}
