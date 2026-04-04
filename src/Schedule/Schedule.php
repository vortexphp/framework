<?php

declare(strict_types=1);

namespace Vortex\Schedule;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Throwable;
use TypeError;
use Vortex\AppContext;
use Vortex\Cache\CacheManager;
use Vortex\Config\Repository;
use Vortex\Container;
use Vortex\Support\Log;

/**
 * Recurring tasks defined in {@code config/schedule.php} and/or registered from {@see \Vortex\Application::boot()} configure callback.
 */
final class Schedule
{
    /** @var list<ScheduledTask> */
    private static array $tasks = [];

    public static function clear(): void
    {
        self::$tasks = [];
    }

    /**
     * @param class-string $handlerClass
     * @param array{without_overlapping?: bool, mutex_ttl?: int, mutex_ttl_seconds?: int} $options
     */
    public static function register(string $cron, string $handlerClass, array $options = []): void
    {
        $cron = trim($cron);
        if ($cron === '' || ! class_exists($handlerClass)) {
            return;
        }

        $withoutOverlapping = (bool) ($options['without_overlapping'] ?? false);
        $mutexTtl = (int) ($options['mutex_ttl'] ?? $options['mutex_ttl_seconds'] ?? 3600);
        $mutexTtl = max(30, min(86400, $mutexTtl));

        self::$tasks[] = new ScheduledTask($cron, $handlerClass, $withoutOverlapping, $mutexTtl);
    }

    public static function loadFromRepository(): void
    {
        $config = Repository::get('schedule', []);
        if (! is_array($config)) {
            return;
        }

        $tasks = $config['tasks'] ?? null;
        if (! is_array($tasks)) {
            return;
        }

        foreach ($tasks as $row) {
            if (! is_array($row)) {
                continue;
            }
            $cron = isset($row['cron']) && is_string($row['cron']) ? trim($row['cron']) : '';
            $class = isset($row['class']) && is_string($row['class']) ? trim($row['class']) : '';
            if ($cron === '' || $class === '' || ! class_exists($class)) {
                continue;
            }

            $withoutOverlapping = (bool) ($row['without_overlapping'] ?? false);
            $mutexTtl = (int) ($row['mutex_ttl'] ?? $row['mutex_ttl_seconds'] ?? 3600);
            $mutexTtl = max(30, min(86400, $mutexTtl));

            self::$tasks[] = new ScheduledTask($cron, $class, $withoutOverlapping, $mutexTtl);
        }
    }

    /**
     * Runs every task whose cron matches {@code $at} (or "now" in app timezone when null).
     *
     * @return non-negative-int number of tasks executed (not skipped)
     */
    public static function runDue(?DateTimeImmutable $at = null): int
    {
        $container = AppContext::container();

        if ($at === null) {
            $tzName = Repository::get('app.timezone', 'UTC');
            $tz = new DateTimeZone(is_string($tzName) && $tzName !== '' ? $tzName : 'UTC');
            $at = new DateTimeImmutable('now', $tz);
        }

        $scheduleConfig = [];
        if (Repository::initialized()) {
            $raw = Repository::get('schedule', []);
            $scheduleConfig = is_array($raw) ? $raw : [];
        }

        $mutexStoreName = isset($scheduleConfig['mutex_store']) && is_string($scheduleConfig['mutex_store']) && $scheduleConfig['mutex_store'] !== ''
            ? $scheduleConfig['mutex_store']
            : null;

        $ran = 0;
        foreach (self::$tasks as $task) {
            try {
                if (! CronExpression::isDue($task->cron, $at)) {
                    continue;
                }
            } catch (InvalidArgumentException $e) {
                Log::error('Invalid cron for scheduled task: ' . $e->getMessage(), [
                    'class' => $task->handlerClass,
                    'cron' => $task->cron,
                ]);
                continue;
            }

            $run = static function () use ($container, $task): void {
                self::invokeHandler($container, $task->handlerClass);
            };

            if ($task->withoutOverlapping) {
                $mutexCache = $container->make(CacheManager::class)->store($mutexStoreName);
                $key = 'schedule:mutex:' . hash('sha256', $task->cron . "\0" . $task->handlerClass);
                if (! $mutexCache->add($key, 1, $task->mutexTtlSeconds)) {
                    continue;
                }
                try {
                    try {
                        $run();
                        ++$ran;
                    } catch (Throwable $e) {
                        Log::exception($e);
                    }
                } finally {
                    $mutexCache->forget($key);
                }

                continue;
            }

            try {
                $run();
                ++$ran;
            } catch (Throwable $e) {
                Log::exception($e);
            }
        }

        return $ran;
    }

    /**
     * @param class-string $class
     */
    private static function invokeHandler(Container $container, string $class): void
    {
        $handler = $container->make($class);
        if (is_callable($handler)) {
            $handler();

            return;
        }
        if (is_callable([$handler, 'handle'])) {
            $handler->handle();

            return;
        }

        throw new TypeError(sprintf('Scheduled handler %s must be invokable or define handle().', $class));
    }

    /**
     * @internal
     */
    public static function resetForTesting(): void
    {
        self::clear();
    }
}
