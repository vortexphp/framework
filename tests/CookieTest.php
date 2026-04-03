<?php

declare(strict_types=1);

namespace Vortex\Tests;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Vortex\Http\Cookie;
use Vortex\Http\Response;

final class CookieTest extends TestCase
{
    protected function tearDown(): void
    {
        Cookie::resetQueue();
        parent::tearDown();
    }

    public function testToHeaderValueMinimal(): void
    {
        $c = new Cookie('sid', 'abc123');

        self::assertSame(
            'sid=abc123; Path=/; HttpOnly; SameSite=Lax',
            $c->toHeaderValue(),
        );
    }

    public function testToHeaderValueWithOptions(): void
    {
        $expires = new DateTimeImmutable('2026-01-15 12:00:00', new DateTimeZone('UTC'));
        $c = new Cookie(
            't',
            'v',
            path: '/app',
            domain: 'example.com',
            maxAge: 3600,
            expires: $expires,
            secure: true,
            httpOnly: true,
            sameSite: 'strict',
        );

        $line = $c->toHeaderValue();
        self::assertStringContainsString('t=v', $line);
        self::assertStringContainsString('Path=/app', $line);
        self::assertStringContainsString('Domain=example.com', $line);
        self::assertStringContainsString('Max-Age=3600', $line);
        self::assertStringContainsString('Expires=', $line);
        self::assertStringContainsString('Secure', $line);
        self::assertStringContainsString('HttpOnly', $line);
        self::assertStringContainsString('SameSite=Strict', $line);
    }

    public function testParseRequestHeader(): void
    {
        $parsed = Cookie::parseRequestHeader('a=1; b=two%20x; c=');

        self::assertSame(['a' => '1', 'b' => 'two x', 'c' => ''], $parsed);
    }

    public function testParseQuotedValue(): void
    {
        $parsed = Cookie::parseRequestHeader('q="a;b"');

        self::assertSame(['q' => 'a;b'], $parsed);
    }

    public function testNormalizedSameSite(): void
    {
        self::assertSame('None', Cookie::normalizedSameSite('none'));
        self::assertSame('Strict', Cookie::normalizedSameSite('STRICT'));
        self::assertSame('Lax', Cookie::normalizedSameSite(''));
    }

    public function testInvalidNameThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Cookie('', 'x');
    }

    public function testQueueFlushQueued(): void
    {
        Cookie::queue(new Cookie('a', '1'));
        Cookie::queue(new Cookie('b', '2'));
        $r = Cookie::flushQueued(Response::html(''));

        $ref = new \ReflectionClass($r);
        $prop = $ref->getProperty('headers');
        /** @var array<string, string|string[]> $headers */
        $headers = $prop->getValue($r);
        $sc = $headers['Set-Cookie'] ?? null;
        self::assertIsArray($sc);
        self::assertCount(2, $sc);
    }

    public function testResetQueueDiscards(): void
    {
        Cookie::queue(new Cookie('x', 'y'));
        Cookie::resetQueue();
        $r = Cookie::flushQueued(Response::html(''));
        $headers = (new \ReflectionClass($r))->getProperty('headers')->getValue($r);
        self::assertArrayNotHasKey('Set-Cookie', $headers);
    }
}
