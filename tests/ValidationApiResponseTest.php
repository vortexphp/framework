<?php

declare(strict_types=1);

namespace Vortex\Tests;

use LogicException;
use PHPUnit\Framework\TestCase;
use Vortex\Http\Response;
use Vortex\Validation\Validator;

final class ValidationApiResponseTest extends TestCase
{
    public function testValidationFailedReturnsApiErrorEnvelope(): void
    {
        $result = Validator::make(['email' => 'nope'], ['email' => 'required|email']);
        self::assertTrue($result->failed());

        $r = Response::validationFailed($result);

        self::assertSame(422, $r->httpStatus());
        self::assertSame(
            '{"ok":false,"error":"validation_failed","message":"Validation failed","errors":{"email":"The email must be a valid email address."}}',
            $r->body(),
        );
    }

    public function testValidationFailedThrowsWhenResultPassed(): void
    {
        $result = Validator::make(['email' => 'ok@example.com'], ['email' => 'required|email']);
        self::assertFalse($result->failed());

        $this->expectException(LogicException::class);
        Response::validationFailed($result);
    }
}
