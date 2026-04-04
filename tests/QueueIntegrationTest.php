<?php

declare(strict_types=1);

namespace Vortex\Tests;

use PHPUnit\Framework\TestCase;
use Vortex\AppContext;
use Vortex\Application;
use Vortex\Config\Repository;
use Vortex\Console\ConsoleApplication;
use Vortex\Console\Commands\QueueWorkCommand;
use Vortex\Console\Input;
use Vortex\Database\Connection;
use Vortex\Queue\Queue;

final class QueueIntegrationTest extends TestCase
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

        DatabaseQueueCountingJob::$total = 0;

        parent::tearDown();
    }

    public function testQueuePushAfterBootInsertsRow(): void
    {
        $this->tempBase = $this->makeTempApp();
        $app = Application::boot($this->tempBase, function (\Vortex\Container $c): void {
            $this->ensureJobsTable($c->make(Connection::class));
        });
        DatabaseQueueCountingJob::$total = 0;
        Queue::push(new DatabaseQueueCountingJob(5));

        $conn = $app->container()->make(Connection::class);
        $row = $conn->selectOne('SELECT COUNT(*) AS c FROM jobs', []);
        self::assertSame(1, (int) ($row['c'] ?? 0));
    }

    public function testQueueWorkOnceProcessesJob(): void
    {
        $this->tempBase = $this->makeTempApp();

        Application::boot($this->tempBase, function (\Vortex\Container $c): void {
            $this->ensureJobsTable($c->make(Connection::class));
        });
        DatabaseQueueCountingJob::$total = 0;
        Queue::push(new DatabaseQueueCountingJob(11));

        $cmd = new QueueWorkCommand($this->tempBase);
        $exit = $cmd->run(Input::fromArgv(['vortex', 'queue:work', 'once']));
        self::assertSame(0, $exit);
        self::assertSame(11, DatabaseQueueCountingJob::$total);

        $app = Application::boot($this->tempBase);
        $row = $app->container()->make(Connection::class)->selectOne('SELECT COUNT(*) AS c FROM jobs', []);
        self::assertSame(0, (int) ($row['c'] ?? 0));
    }

    public function testConsoleRegistersQueueWorkCommand(): void
    {
        $this->tempBase = $this->makeTempApp();
        $this->primeJobsSchemaFile();

        $app = ConsoleApplication::boot($this->tempBase);
        self::assertSame(0, $app->run(['php', 'queue:work', 'once']));
    }

    private function primeJobsSchemaFile(): void
    {
        $dbPath = $this->tempBase . '/queue.sqlite';
        $pdo = new \PDO('sqlite:' . $dbPath, null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE IF NOT EXISTS jobs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            queue VARCHAR(255) NOT NULL,
            payload TEXT NOT NULL,
            attempts INTEGER NOT NULL DEFAULT 0,
            reserved_at INTEGER NULL,
            available_at INTEGER NOT NULL,
            created_at INTEGER NOT NULL
        )');
    }

    private function ensureJobsTable(Connection $db): void
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
    }

    /**
     * @return non-empty-string
     */
    private function makeTempApp(): string
    {
        $fixture = __DIR__ . '/Fixtures/minimal-http-app';
        $base = sys_get_temp_dir() . '/vortex_queue_int_' . bin2hex(random_bytes(8));
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
