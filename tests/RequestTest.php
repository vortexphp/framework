<?php

declare(strict_types=1);

namespace Vortex\Tests;

use PHPUnit\Framework\TestCase;
use Vortex\Http\Request;
use Vortex\Http\UploadedFile;

final class RequestTest extends TestCase
{
    protected function tearDown(): void
    {
        Request::forgetCurrent();
        parent::tearDown();
    }

    public function testInputPrefersBodyOverQuery(): void
    {
        $r = new Request('POST', '/', ['a' => 'q'], ['a' => 'b'], [], []);
        Request::setCurrent($r);

        self::assertSame('b', Request::input('a'));
    }

    public function testAllMergesQueryAndBodyBodyWins(): void
    {
        $r = new Request('POST', '/', ['q' => '1', 'only' => 'q'], ['q' => '2', 'b' => 'y'], [], []);
        Request::setCurrent($r);

        self::assertSame(['q' => '2', 'only' => 'q', 'b' => 'y'], Request::all());
    }

    public function testInputFallsBackToQueryThenDefault(): void
    {
        $r = new Request('GET', '/', ['x' => '1'], [], [], []);
        Request::setCurrent($r);

        self::assertSame('1', Request::input('x'));
        self::assertNull(Request::input('missing'));
        self::assertSame('d', Request::input('missing', 'd'));
    }

    public function testHeaderNormalizesName(): void
    {
        $r = new Request('GET', '/', [], [], ['Accept' => 'application/json'], []);
        Request::setCurrent($r);

        self::assertSame('application/json', Request::header('accept'));
        self::assertSame('application/json', Request::header('ACCEPT'));
    }

    public function testWantsJson(): void
    {
        $yes = new Request('GET', '/', [], [], ['Accept' => 'text/html, application/json'], []);
        Request::setCurrent($yes);
        self::assertTrue(Request::wantsJson());

        $no = new Request('GET', '/', [], [], ['Accept' => 'text/html'], []);
        Request::setCurrent($no);
        self::assertFalse(Request::wantsJson());
    }

    public function testIsSecure(): void
    {
        $on = new Request('GET', '/', [], [], [], ['HTTPS' => 'on']);
        Request::setCurrent($on);
        self::assertTrue(Request::isSecure());

        $off = new Request('GET', '/', [], [], [], ['HTTPS' => 'off']);
        Request::setCurrent($off);
        self::assertFalse(Request::isSecure());

        $empty = new Request('GET', '/', [], [], [], []);
        Request::setCurrent($empty);
        self::assertFalse(Request::isSecure());
    }

    public function testFileReturnsUploadedFileOrNull(): void
    {
        $upload = new UploadedFile('a.png', '/tmp/x', UPLOAD_ERR_OK, 10);
        $r = new Request('POST', '/', [], [], [], [], ['avatar' => $upload]);
        Request::setCurrent($r);

        self::assertSame($upload, Request::file('avatar'));
        self::assertNull(Request::file('missing'));
    }
}
