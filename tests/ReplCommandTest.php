<?php

declare(strict_types=1);

namespace Vortex\Tests;

use PHPUnit\Framework\TestCase;
use Vortex\AppContext;
use Vortex\Config\Repository;
use Vortex\Console\Commands\ReplCommand;
use Vortex\Console\Input;
use Vortex\Routing\Router;

final class ReplCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        Repository::forgetInstance();
        $ref = new \ReflectionClass(AppContext::class);
        $p = $ref->getProperty('container');
        $p->setAccessible(true);
        $p->setValue(null, null);
        parent::tearDown();
    }

    public function testReplRefusesWhenDebugOffAndNoForce(): void
    {
        $base = $this->makeTempProject(debug: false);
        try {
            $cmd = new ReplCommand($base);
            $rc = $cmd->run(Input::fromArgv(['php', 'repl']));
            self::assertSame(1, $rc);
        } finally {
            $this->removeTree($base);
        }
    }

    public function testReplRunsWithForceAndEvaluatesExpression(): void
    {
        $base = $this->makeTempProject(debug: false);
        try {
            $state = (object) ['i' => 0];
            $lines = [
                '$c->has(' . Router::class . '::class)',
                'exit',
            ];
            $cmd = new class ($base, $lines, $state) extends ReplCommand {
                /**
                 * @param list<string> $lines
                 */
                public function __construct(string $projectBasePath, private readonly array $lines, private readonly object $state)
                {
                    parent::__construct($projectBasePath);
                }

                protected function readLine(): string|false
                {
                    $i = $this->state->i++;

                    return $this->lines[$i] ?? false;
                }
            };

            $rc = $cmd->run(Input::fromArgv(['php', 'repl', '--force']));
            self::assertSame(0, $rc);
        } finally {
            $this->removeTree($base);
        }
    }

    /**
     * @return non-empty-string
     */
    private function makeTempProject(bool $debug): string
    {
        $fixture = __DIR__ . '/Fixtures/minimal-http-app';
        $base = sys_get_temp_dir() . '/vortex-repl-' . bin2hex(random_bytes(8));
        mkdir($base . '/config', 0777, true);
        mkdir($base . '/app/Routes', 0777, true);
        mkdir($base . '/public', 0777, true);
        mkdir($base . '/assets/views', 0777, true);
        mkdir($base . '/lang', 0777, true);
        mkdir($base . '/storage/cache/twig', 0777, true);
        mkdir($base . '/storage/logs', 0777, true);

        foreach (['broadcasting.php', 'cache.php', 'database.php', 'events.php', 'mail.php'] as $f) {
            copy($fixture . '/config/' . $f, $base . '/config/' . $f);
        }

        $appConfig = file_get_contents($fixture . '/config/app.php');
        if (! is_string($appConfig)) {
            throw new \RuntimeException('Fixture app.php missing');
        }
        if (! $debug) {
            $appConfig = str_replace("'debug' => true", "'debug' => false", $appConfig);
        }
        file_put_contents($base . '/config/app.php', $appConfig);

        file_put_contents(
            $base . '/public/index.php',
            <<<'PHP'
<?php
declare(strict_types=1);
PHP,
        );

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
        foreach ($it as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    }
}
