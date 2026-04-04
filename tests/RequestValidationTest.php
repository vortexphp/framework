<?php

declare(strict_types=1);

namespace Vortex\Tests;

use PHPUnit\Framework\TestCase;
use Vortex\Http\Request;

final class RequestValidationTest extends TestCase
{
    public function testValidationResponseReturns422WhenMergedInputInvalid(): void
    {
        $req = Request::make('POST', '/api', ['n' => '1'], ['email' => 'bad'], [], ['Accept' => 'application/json']);

        $bad = $req->validationResponse(['email' => 'required|email']);
        self::assertNotNull($bad);
        self::assertSame(422, $bad->httpStatus());
        self::assertStringContainsString('validation_failed', $bad->body());

        $ok = Request::make('POST', '/api', [], ['email' => 'u@v.test'], [], ['Accept' => 'application/json']);
        self::assertNull($ok->validationResponse(['email' => 'required|email']));
    }

    public function testBodyValidationResponseUsesOnlyBodyNotQuery(): void
    {
        $req = Request::make('POST', '/api', ['email' => 'bad-in-query'], ['email' => 'ok@example.com'], [], ['Accept' => 'application/json']);
        self::assertNull($req->bodyValidationResponse(['email' => 'required|email']));

        $req2 = Request::make('POST', '/api', ['email' => 'ok@example.com'], ['email' => 'not-email'], [], ['Accept' => 'application/json']);
        self::assertNotNull($req2->bodyValidationResponse(['email' => 'required|email']));
    }

    public function testCurrentRequestValidationResponse(): void
    {
        $req = Request::make('POST', '/x', [], ['email' => 'x'], [], ['Accept' => 'application/json']);
        Request::setCurrent($req);
        try {
            $r = Request::current()->validationResponse(['email' => 'required|email']);
            self::assertNotNull($r);
            self::assertSame(422, $r->httpStatus());
        } finally {
            Request::forgetCurrent();
        }
    }
}
