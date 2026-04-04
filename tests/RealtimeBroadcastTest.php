<?php

declare(strict_types=1);

namespace Vortex\Tests;

use PHPUnit\Framework\TestCase;
use Vortex\AppContext;
use Vortex\Application;
use Vortex\Broadcasting\Contracts\Broadcaster;
use Vortex\Broadcasting\SyncBroadcaster;
use Vortex\Config\Repository;
use Vortex\Http\Response;
use Vortex\Http\SseEmitter;
use Vortex\Schedule\Schedule;

final class RealtimeBroadcastTest extends TestCase
{
    protected function tearDown(): void
    {
        Schedule::resetForTesting();
        Repository::forgetInstance();
        $ref = new \ReflectionClass(AppContext::class);
        $p = $ref->getProperty('container');
        $p->setAccessible(true);
        $p->setValue(null, null);
        parent::tearDown();
    }

    public function testSyncBroadcasterInvokesListeners(): void
    {
        $b = new SyncBroadcaster();
        $seen = [];
        $b->listen('orders', static function (string $event, array $payload) use (&$seen): void {
            $seen[] = [$event, $payload];
        });
        $b->publish('orders', 'created', ['id' => 9]);

        self::assertSame([['created', ['id' => 9]]], $seen);
    }

    public function testApplicationRegistersBroadcaster(): void
    {
        $fixture = __DIR__ . '/Fixtures/minimal-http-app';
        $base = sys_get_temp_dir() . '/vortex-broadcast-' . bin2hex(random_bytes(8));
        mkdir($base . '/config', 0777, true);
        mkdir($base . '/app/Routes', 0777, true);
        mkdir($base . '/public', 0777, true);
        mkdir($base . '/assets/views', 0777, true);
        mkdir($base . '/lang', 0777, true);
        mkdir($base . '/storage/cache/twig', 0777, true);
        mkdir($base . '/storage/logs', 0777, true);
        foreach (['app.php', 'broadcasting.php', 'cache.php', 'database.php', 'events.php', 'mail.php'] as $f) {
            copy($fixture . '/config/' . $f, $base . '/config/' . $f);
        }

        try {
            $app = Application::boot($base);
            $c = $app->container();
            self::assertTrue($c->has(Broadcaster::class));
            self::assertInstanceOf(SyncBroadcaster::class, $c->make(Broadcaster::class));
        } finally {
            $this->removeDir($base);
        }
    }

    public function testSseEmitterMessageFormat(): void
    {
        $out = '';
        $e = new SseEmitter(static function (string $chunk) use (&$out): void {
            $out .= $chunk;
        });
        $e->message('1', 'ping', "line1\nline2");

        self::assertStringContainsString("id: 1\n", $out);
        self::assertStringContainsString("event: ping\n", $out);
        self::assertStringContainsString("data: line1\n", $out);
        self::assertStringContainsString("data: line2\n", $out);
        self::assertStringEndsWith("\n\n", $out);
    }

    public function testServerSentEventsSetsHeadersAndStreamFlag(): void
    {
        $r = Response::serverSentEvents(static function (SseEmitter $sse): void {
            $sse->json('a', 'tick', ['n' => 2]);
        });
        self::assertTrue($r->isStreamResponse());
        self::assertSame('', $r->body());
        self::assertStringContainsString('text/event-stream', (string) ($r->headers()['Content-Type'] ?? ''));
    }

    private function removeDir(string $dir): void
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
