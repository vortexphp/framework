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

final class QueryBuilderFeaturesTest extends TestCase
{
    private string $configDir = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->configDir = sys_get_temp_dir() . '/vortex-qb-features-' . bin2hex(random_bytes(4));
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

        QbThread::connection()->execute(
            'CREATE TABLE qb_threads (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, title TEXT NOT NULL, created_at TEXT NULL)'
        );
        QbPost::connection()->execute(
            'CREATE TABLE qb_posts (id INTEGER PRIMARY KEY AUTOINCREMENT, thread_id INTEGER NOT NULL, body TEXT NOT NULL, created_at TEXT NULL)'
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

    public function testJoinGroupByAndGetRaw(): void
    {
        $threadA = QbThread::create(['user_id' => 7, 'title' => 'A', 'created_at' => '2026-01-01 00:00:00']);
        $threadB = QbThread::create(['user_id' => 9, 'title' => 'B', 'created_at' => '2026-01-01 00:00:00']);
        QbPost::create(['thread_id' => (int) $threadA->id, 'body' => 'one']);
        QbPost::create(['thread_id' => (int) $threadA->id, 'body' => 'two']);
        QbPost::create(['thread_id' => (int) $threadB->id, 'body' => 'three']);

        $rows = QbThread::query()
            ->select([
                'qb_threads.id',
                'qb_threads.title',
                'COUNT(qb_posts.id) AS posts_count',
            ])
            ->leftJoin('qb_posts', 'qb_posts.thread_id', '=', 'qb_threads.id')
            ->groupBy(['qb_threads.id', 'qb_threads.title'])
            ->orderBy('qb_threads.id')
            ->getRaw();

        self::assertCount(2, $rows);
        self::assertSame(2, (int) ($rows[0]['posts_count'] ?? 0));
        self::assertSame(1, (int) ($rows[1]['posts_count'] ?? 0));
    }

    public function testGroupedWhereAndOrWhereWork(): void
    {
        QbThread::create(['user_id' => 7, 'title' => 'Alpha']);
        QbThread::create(['user_id' => 7, 'title' => 'Beta']);
        QbThread::create(['user_id' => 8, 'title' => 'Gamma']);

        $rows = QbThread::query()
            ->whereGroup(static function ($query): void {
                $query->where('user_id', 7)->orWhere('title', 'Gamma');
            })
            ->orderBy('id')
            ->get();

        self::assertCount(3, $rows);
        self::assertSame('Alpha', (string) ($rows[0]->title ?? ''));
        self::assertSame('Gamma', (string) ($rows[2]->title ?? ''));
    }

    public function testUpdateDeleteAndMultipleOrderBy(): void
    {
        $first = QbThread::create(['user_id' => 1, 'title' => 'Same', 'created_at' => '2026-01-01 00:00:00']);
        $second = QbThread::create(['user_id' => 1, 'title' => 'Same', 'created_at' => '2026-01-01 00:00:00']);

        $updated = QbThread::query()->where('id', (int) $first->id)->update(['title' => 'Changed']);
        self::assertSame(1, $updated);
        self::assertSame('Changed', (string) (QbThread::find((int) $first->id)?->title ?? ''));

        $ordered = QbThread::query()
            ->where('user_id', 1)
            ->orderBy('created_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->pluck('id');
        self::assertSame([(int) $second->id, (int) $first->id], array_map(static fn (mixed $id): int => (int) $id, $ordered));

        $deleted = QbThread::query()->where('id', (int) $second->id)->delete();
        self::assertSame(1, $deleted);
        self::assertNull(QbThread::find((int) $second->id));
    }

    private function clearAppContext(): void
    {
        $ref = new \ReflectionClass(AppContext::class);
        $prop = $ref->getProperty('container');
        $prop->setAccessible(true);
        $prop->setValue(null, null);
    }
}

final class QbThread extends Model
{
    protected static ?string $table = 'qb_threads';

    /** @var list<string> */
    protected static array $fillable = ['user_id', 'title', 'created_at'];

    protected static bool $timestamps = false;
}

final class QbPost extends Model
{
    protected static ?string $table = 'qb_posts';

    /** @var list<string> */
    protected static array $fillable = ['thread_id', 'body', 'created_at'];

    protected static bool $timestamps = false;
}
