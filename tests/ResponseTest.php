<?php

declare(strict_types=1);

namespace Vortex\Tests;

use PHPUnit\Framework\TestCase;
use Vortex\AppContext;
use Vortex\Container;
use Vortex\Http\Cookie;
use Vortex\Http\NullSessionStore;
use Vortex\Http\Response;
use Vortex\Http\Request;
use Vortex\Http\Session;
use Vortex\Http\SessionManager;

final class ResponseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $container = new Container();
        $container->instance(Container::class, $container);
        $container->singleton(SessionManager::class, static fn (): SessionManager => SessionManager::fromInstances('null', [
            'null' => new NullSessionStore(),
        ]));
        $container->singleton(Session::class, static fn (Container $c): Session => new Session($c->make(SessionManager::class)->store()));
        Session::setInstance($container->make(Session::class));
        AppContext::set($container);
    }

    protected function tearDown(): void
    {
        $refApp = new \ReflectionClass(AppContext::class);
        $propApp = $refApp->getProperty('container');
        $propApp->setAccessible(true);
        $propApp->setValue(null, null);

        $refSession = new \ReflectionClass(Session::class);
        $propSession = $refSession->getProperty('instance');
        $propSession->setAccessible(true);
        $propSession->setValue(null, null);

        Request::forgetCurrent();

        parent::tearDown();
    }

    public function testJsonSetsContentTypeAndBody(): void
    {
        $r = Response::json(['ok' => true], 201);

        self::assertSame(201, $r->httpStatus());
        self::assertSame('{"ok":true}', $r->body());
        self::assertSame(
            'application/json; charset=utf-8',
            $this->headerValue($r, 'Content-Type'),
        );
    }

    public function testHtmlSetsCharsetContentType(): void
    {
        $r = Response::html('<p>x</p>', 200);

        self::assertSame(200, $r->httpStatus());
        self::assertSame('<p>x</p>', $r->body());
        self::assertSame(
            'text/html; charset=utf-8',
            $this->headerValue($r, 'Content-Type'),
        );
    }

    public function testHtmlHeadersOverrideDefaultContentType(): void
    {
        $r = Response::html('', 200, ['Content-Type' => 'text/plain; charset=utf-8']);

        self::assertSame('text/plain; charset=utf-8', $this->headerValue($r, 'Content-Type'));
    }

    public function testRedirect(): void
    {
        $r = Response::redirect('/here', 301);

        self::assertSame(301, $r->httpStatus());
        self::assertSame('', $r->body());
        self::assertSame('/here', $this->headerValue($r, 'Location'));
    }

    public function testRedirectWithFluentFlashHelpers(): void
    {
        Request::setCurrent(Request::make('POST', '/login', [], [
            'email' => 'dev@example.com',
            '_token' => 'abc',
        ]));

        Response::redirect('/login')
            ->withErrors(['email' => 'invalid'])
            ->withInput()
            ->with('status', 'failed');

        self::assertSame(['email' => 'invalid'], Session::flash('errors'));
        self::assertSame(['email' => 'dev@example.com', '_token' => 'abc'], Session::flash('old'));
        self::assertSame('failed', Session::flash('status'));
    }

    public function testWithSecurityHeadersDoesNotOverrideExisting(): void
    {
        $r = Response::make('', 200, ['X-Frame-Options' => 'DENY'])->withSecurityHeaders();

        self::assertSame('DENY', $this->headerValue($r, 'X-Frame-Options'));
        self::assertSame('nosniff', $this->headerValue($r, 'X-Content-Type-Options'));
    }

    public function testCookieAppendsSetCookie(): void
    {
        $r = Response::html('')
            ->cookie(new Cookie('a', '1'))
            ->cookie(new Cookie('b', '2'));

        $ref = new \ReflectionClass($r);
        $prop = $ref->getProperty('headers');
        /** @var array<string, string|string[]> $headers */
        $headers = $prop->getValue($r);
        $sc = $headers['Set-Cookie'] ?? null;
        self::assertIsArray($sc);
        self::assertCount(2, $sc);
        self::assertStringStartsWith('a=1', (string) $sc[0]);
        self::assertStringStartsWith('b=2', (string) $sc[1]);
    }

    /**
     * @param Response $r
     */
    private function headerValue(Response $r, string $name): string
    {
        $ref = new \ReflectionClass($r);
        $prop = $ref->getProperty('headers');
        /** @var array<string, string|string[]> $headers */
        $headers = $prop->getValue($r);
        $v = $headers[$name] ?? null;

        return is_array($v) ? (string) ($v[0] ?? '') : (string) $v;
    }
}
