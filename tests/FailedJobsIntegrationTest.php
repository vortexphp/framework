<?php

declare(strict_types=1);

namespace Vortex\Tests;

use PHPUnit\Framework\TestCase;
use Vortex\AppContext;
use Vortex\Application;
use Vortex\Config\Repository;
use Vortex\Console\Commands\QueueRetryCommand;
use Vortex\Console\Commands\QueueWorkCommand;
use Vortex\Console\Input;
use Vortex\Database\Connection;
use Vortex\Queue\Contracts\Job;
use Vortex\Queue\Queue;
use RuntimeException;

final class FailedJobsIntegrationTest extends TestCase
{
    private string $tempBase = '';

    protected function tearDown(): void
    {
        if ($this->tempBase !== '' && is_dir($this->tempBase)) {
            $this->removeTree($this->tempBase);
        }

        Repository::forgetInstance();
        $refApp = new \ReflectionClass(AppContext::class);
        $p = $refApp->getProperty('container');
        $p->setAccessible(true);
        $p->setValue(null, null);

        FailOnceJob::$runs = 0;

        parent::tearDown();
    }

    public function testPermanentFailureIsRecordedAndRetryProcesses(): void
    {
        $this->tempBase = $this->makeTempApp();

        Application::boot($this->tempBase, function (\Vortex\Container $c): void {
            $this->ensureTables($c->make(Connection::class));
        });

        Queue::push(new FailOnceJob());

        $work = new QueueWorkCommand($this->tempBase);
        self::assertSame(0, $work->run(Input::fromArgv(['vortex', 'queue:work', 'once'])));

        $app = Application::boot($this->tempBase);
        $conn = $app->container()->make(Connection::class);
        $failed = $conn->selectOne('SELECT COUNT(*) AS c FROM failed_jobs', []);
        self::assertSame(1, (int) $failed['c']);
        $jobs = $conn->selectOne('SELECT COUNT(*) AS c FROM jobs', []);
        self::assertSame(0, (int) $jobs['c']);

        $retry = new QueueRetryCommand($this->tempBase);
        self::assertSame(0, $retry->run(Input::fromArgv(['vortex', 'queue:retry', '1'])));

        $jobs = $conn->selectOne('SELECT COUNT(*) AS c FROM jobs', []);
        self::assertSame(1, (int) $jobs['c']);
        $failed = $conn->selectOne('SELECT COUNT(*) AS c FROM failed_jobs', []);
        self::assertSame(0, (int) $failed['c']);

        self::assertSame(0, $work->run(Input::fromArgv(['vortex', 'queue:work', 'once'])));
        self::assertSame(2, FailOnceJob::$runs);
    }

    public function testPushSerializedRoundTrip(): void
    {
        $pdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE jobs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            queue VARCHAR(255) NOT NULL,
            payload TEXT NOT NULL,
            attempts INTEGER NOT NULL DEFAULT 0,
            reserved_at INTEGER NULL,
            available_at INTEGER NOT NULL,
            created_at INTEGER NOT NULL
        )');
        $db = new Connection($pdo);
        $q = new \Vortex\Queue\DatabaseQueue($db, 'jobs');
        $q->pushSerialized('default', serialize(new FailOnceJob()));
        $row = $db->selectOne('SELECT COUNT(*) AS c FROM jobs', []);
        self::assertSame(1, (int) $row['c']);
    }

    private function ensureTables(Connection $db): void
    {
        $db->execute('CREATE TABLE IF NOT EXISTS jobs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            queue VARCHAR(255) NOT NULL,
            payload TEXT NOT NULL,
            attempts INTEGER NOT NULL DEFAULT 0,
            reserved_at INTEGER NULL,
            available_at INTEGER NOT NULL,
            created_at INTEGER NOT NULL
        )');
        $db->execute('CREATE TABLE IF NOT EXISTS failed_jobs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            queue VARCHAR(255) NOT NULL,
            payload TEXT NOT NULL,
            exception TEXT NOT NULL,
            failed_at INTEGER NOT NULL
        )');
    }

    /**
     * @return non-empty-string
     */
    private function makeTempApp(): string
    {
        $fixture = __DIR__ . '/Fixtures/minimal-http-app';
        $base = sys_get_temp_dir() . '/vortex_failed_jobs_' . bin2hex(random_bytes(8));
        mkdir($base . '/config', 0777, true);
        mkdir($base . '/app/Routes', 0777, true);
        mkdir($base . '/public', 0777, true);
        mkdir($base . '/assets/views', 0777, true);
        mkdir($base . '/lang', 0777, true);
        mkdir($base . '/storage/cache/twig', 0777, true);
        mkdir($base . '/storage/logs', 0777, true);

        $dbPath = $base . '/queue.sqlite';
        copy($fixture . '/config/app.php', $base . '/config/app.php');
        copy($fixture . '/config/cache.php', $base . '/config/cache.php');
        copy($fixture . '/config/events.php', $base . '/config/events.php');
        copy($fixture . '/config/mail.php', $base . '/config/mail.php');

        file_put_contents($base . '/config/database.php', <<<PHP
<?php
declare(strict_types=1);
return [
    'default' => 'default',
    'connections' => [
        'default' => [
            'driver' => 'sqlite',
            'database' => '{$dbPath}',
            'host' => '',
            'port' => '',
            'username' => '',
            'password' => '',
        ],
    ],
];
PHP);

        file_put_contents($base . '/config/queue.php', <<<'PHP'
<?php
declare(strict_types=1);
return [
    'table' => 'jobs',
    'default' => 'default',
    'tries' => 1,
    'stale_reserve_seconds' => 300,
    'idle_sleep_ms' => 1000,
    'failed_jobs_table' => 'failed_jobs',
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

final class FailOnceJob implements Job
{
    public static int $runs = 0;

    public function handle(): void
    {
        self::$runs++;
        if (self::$runs === 1) {
            throw new RuntimeException('first run fails');
        }
    }
}
