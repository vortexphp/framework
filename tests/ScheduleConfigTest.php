<?php

declare(strict_types=1);

namespace Vortex\Tests;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Vortex\AppContext;
use Vortex\Application;
use Vortex\Config\Repository;
use Vortex\Schedule\Schedule;

final class ScheduleConfigTest extends TestCase
{
    private string $tempBase = '';

    protected function tearDown(): void
    {
        if ($this->tempBase !== '' && is_dir($this->tempBase)) {
            $this->removeTree($this->tempBase);
        }
        Schedule::resetForTesting();
        Repository::forgetInstance();
        $ref = new \ReflectionClass(AppContext::class);
        $p = $ref->getProperty('container');
        $p->setAccessible(true);
        $p->setValue(null, null);
        ScheduleFromConfigHandler::$hits = 0;
        parent::tearDown();
    }

    public function testLoadFromRepositoryRunsConfiguredClass(): void
    {
        $this->tempBase = $this->makeTempAppWithSchedule();
        Application::boot($this->tempBase);

        $c = AppContext::container();
        self::assertSame(0, ScheduleFromConfigHandler::$hits);

        $at = new DateTimeImmutable('2030-01-01 00:00:00', new DateTimeZone('UTC'));
        self::assertSame(1, Schedule::runDue($at));
        self::assertSame(1, ScheduleFromConfigHandler::$hits);
    }

    /**
     * @return non-empty-string
     */
    private function makeTempAppWithSchedule(): string
    {
        $fixture = __DIR__ . '/Fixtures/minimal-http-app';
        $base = sys_get_temp_dir() . '/vortex_schedule_cfg_' . bin2hex(random_bytes(8));
        mkdir($base . '/config', 0777, true);
        mkdir($base . '/app/Routes', 0777, true);
        mkdir($base . '/public', 0777, true);
        mkdir($base . '/assets/views', 0777, true);
        mkdir($base . '/lang', 0777, true);
        mkdir($base . '/storage/cache/twig', 0777, true);
        mkdir($base . '/storage/logs', 0777, true);

        foreach (['app.php', 'cache.php', 'database.php', 'events.php', 'mail.php'] as $f) {
            copy($fixture . '/config/' . $f, $base . '/config/' . $f);
        }

        file_put_contents($base . '/config/schedule.php', <<<'PHP'
<?php
declare(strict_types=1);
return [
    'tasks' => [
        ['cron' => '0 0 * * *', 'class' => \Vortex\Tests\ScheduleFromConfigHandler::class],
    ],
];
PHP);

        return $base;
    }

    private function removeTree(string $dir): void
    {
        if (! is_dir($dir)) {
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

final class ScheduleFromConfigHandler
{
    public static int $hits = 0;

    public function __invoke(): void
    {
        self::$hits++;
    }
}
