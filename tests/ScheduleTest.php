<?php

declare(strict_types=1);

namespace Vortex\Tests;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Vortex\AppContext;
use Vortex\Cache\CacheManager;
use Vortex\Cache\FileCache;
use Vortex\Config\Repository;
use Vortex\Container;
use Vortex\Contracts\Cache as CacheContract;
use Vortex\Schedule\Schedule;

final class ScheduleTest extends TestCase
{
    private ?string $tempScheduleConfigDir = null;

    private ?string $tempMutexCacheDir = null;

    protected function tearDown(): void
    {
        $this->removeTree($this->tempScheduleConfigDir);
        $this->tempScheduleConfigDir = null;
        $this->removeTree($this->tempMutexCacheDir);
        $this->tempMutexCacheDir = null;

        Schedule::resetForTesting();
        Repository::forgetInstance();
        $ref = new \ReflectionClass(AppContext::class);
        $p = $ref->getProperty('container');
        $p->setAccessible(true);
        $p->setValue(null, null);

        ScheduleHeartbeat::$hits = 0;
        ScheduleHandle::$hits = 0;

        parent::tearDown();
    }

    public function testRunDueInvokesCallableHandler(): void
    {
        $c = new Container();
        $c->instance(Container::class, $c);
        AppContext::set($c);

        Schedule::register('* * * * *', ScheduleHeartbeat::class);

        $at = new DateTimeImmutable('2026-04-04 12:30:00', new DateTimeZone('UTC'));
        self::assertSame(1, Schedule::runDue($at));
        self::assertSame(1, ScheduleHeartbeat::$hits);
    }

    public function testSkipsWhenCronDoesNotMatch(): void
    {
        $c = new Container();
        $c->instance(Container::class, $c);
        AppContext::set($c);

        Schedule::register('0 * * * *', ScheduleHeartbeat::class);
        $at = new DateTimeImmutable('2026-04-04 12:30:00', new DateTimeZone('UTC'));
        self::assertSame(0, Schedule::runDue($at));
        self::assertSame(0, ScheduleHeartbeat::$hits);
    }

    public function testHandleMethodIsInvoked(): void
    {
        $c = new Container();
        $c->instance(Container::class, $c);
        AppContext::set($c);

        Schedule::register('* * * * *', ScheduleHandle::class);
        $at = new DateTimeImmutable('2026-04-04 00:00:00', new DateTimeZone('UTC'));
        self::assertSame(1, Schedule::runDue($at));
        self::assertSame(1, ScheduleHandle::$hits);
    }

    public function testWithoutOverlappingAcquiresMutexFromConfiguredStore(): void
    {
        $this->tempScheduleConfigDir = sys_get_temp_dir() . '/vortex_sched_mx_cfg_' . bin2hex(random_bytes(8));
        mkdir($this->tempScheduleConfigDir, 0777, true);
        file_put_contents($this->tempScheduleConfigDir . '/schedule.php', <<<'PHP'
<?php

declare(strict_types=1);

return [
    'mutex_store' => 'mutex',
    'tasks' => [],
];
PHP);
        Repository::setInstance(new Repository($this->tempScheduleConfigDir));

        $this->tempMutexCacheDir = sys_get_temp_dir() . '/vortex_sched_mx_data_' . bin2hex(random_bytes(8));
        mkdir($this->tempMutexCacheDir, 0777, true);
        $mutexCacheDir = $this->tempMutexCacheDir;

        $c = new Container();
        $c->instance(Container::class, $c);
        $c->singleton(CacheManager::class, static function () use ($mutexCacheDir): CacheManager {
            return CacheManager::fromInstances('mutex', [
                'mutex' => new FileCache($mutexCacheDir, 'mtx:'),
            ]);
        });
        AppContext::set($c);

        Schedule::register('* * * * *', ScheduleHeartbeat::class, ['without_overlapping' => true]);
        $at = new DateTimeImmutable('2026-04-04 12:30:00', new DateTimeZone('UTC'));
        self::assertSame(1, Schedule::runDue($at));
        self::assertSame(1, ScheduleHeartbeat::$hits);
    }

    public function testWithoutOverlappingSkippedWhenMutexNotAcquired(): void
    {
        $this->tempScheduleConfigDir = sys_get_temp_dir() . '/vortex_sched_mx_cfg2_' . bin2hex(random_bytes(8));
        mkdir($this->tempScheduleConfigDir, 0777, true);
        file_put_contents($this->tempScheduleConfigDir . '/schedule.php', <<<'PHP'
<?php

declare(strict_types=1);

return [
    'mutex_store' => 'mutex',
    'tasks' => [],
];
PHP);
        Repository::setInstance(new Repository($this->tempScheduleConfigDir));

        $c = new Container();
        $c->instance(Container::class, $c);
        $c->singleton(CacheManager::class, static fn (): CacheManager => CacheManager::fromInstances('mutex', [
            'mutex' => new MutexNeverAcquireCache(),
        ]));
        AppContext::set($c);

        Schedule::register('* * * * *', ScheduleHeartbeat::class, ['without_overlapping' => true]);
        $at = new DateTimeImmutable('2026-04-04 12:30:00', new DateTimeZone('UTC'));
        self::assertSame(0, Schedule::runDue($at));
        self::assertSame(0, ScheduleHeartbeat::$hits);
    }

    private function removeTree(?string $dir): void
    {
        if ($dir === null || $dir === '' || ! is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($it as $file) {
            $path = $file->getPathname();
            $file->isDir() ? @rmdir($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}

final class ScheduleHeartbeat
{
    public static int $hits = 0;

    public function __invoke(): void
    {
        self::$hits++;
    }
}

final class ScheduleHandle
{
    public static int $hits = 0;

    public function handle(): void
    {
        self::$hits++;
    }
}

final class MutexNeverAcquireCache implements CacheContract
{
    public function get(string $key, mixed $default = null): mixed
    {
        return $default;
    }

    public function set(string $key, mixed $value, ?int $ttlSeconds = null): void
    {
    }

    public function add(string $key, mixed $value, int $ttlSeconds): bool
    {
        return false;
    }

    public function forget(string $key): void
    {
    }

    public function clear(): void
    {
    }

    public function remember(string $key, ?int $ttlSeconds, callable $callback): mixed
    {
        return $callback();
    }
}
