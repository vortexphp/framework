<?php

declare(strict_types=1);

namespace Vortex\Tests;

use PHPUnit\Framework\TestCase;
use Vortex\Http\Response;

final class ResponseTest extends TestCase
{
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

    public function testWithSecurityHeadersDoesNotOverrideExisting(): void
    {
        $r = Response::make('', 200, ['X-Frame-Options' => 'DENY'])->withSecurityHeaders();

        self::assertSame('DENY', $this->headerValue($r, 'X-Frame-Options'));
        self::assertSame('nosniff', $this->headerValue($r, 'X-Content-Type-Options'));
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
