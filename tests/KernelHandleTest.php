<?php

declare(strict_types=1);

namespace Vortex\Tests;

use PHPUnit\Framework\TestCase;
use Vortex\Application;
use Vortex\Http\ErrorRenderer;
use Vortex\Http\Kernel;
use Vortex\Http\Request;

final class KernelHandleTest extends TestCase
{
    private string $fixtureBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixtureBase = __DIR__ . '/Fixtures/minimal-http-app';
    }

    protected function tearDown(): void
    {
        Request::forgetCurrent();
        parent::tearDown();
    }

    public function testHandleReturnsResponseWithoutSending(): void
    {
        $app = Application::boot($this->fixtureBase);
        $container = $app->container();
        $container->singleton(ErrorRenderer::class, static fn (): ErrorRenderer => new ErrorRenderer());

        $kernel = new Kernel($container);
        $response = $kernel->handle(Request::make('GET', '/t'));

        self::assertSame(200, $response->httpStatus());
        self::assertSame('ok', $response->body());
    }

    public function testHandleAppliesSecurityHeaders(): void
    {
        $app = Application::boot($this->fixtureBase);
        $container = $app->container();
        $container->singleton(ErrorRenderer::class, static fn (): ErrorRenderer => new ErrorRenderer());

        $kernel = new Kernel($container);
        $response = $kernel->handle(Request::make('GET', '/t'));

        self::assertSame('nosniff', $response->headers()['X-Content-Type-Options']);
    }
}
