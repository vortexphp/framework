<?php

declare(strict_types=1);

namespace Vortex\Tests;

use PHPUnit\Framework\TestCase;
use Vortex\Auth\AuthorizationException;
use Vortex\Http\ErrorRenderer;
use Vortex\Http\Request;

final class AuthorizationExceptionRenderTest extends TestCase
{
    protected function tearDown(): void
    {
        Request::forgetCurrent();
        parent::tearDown();
    }

    public function testErrorRendererReturns403JsonForAuthorizationException(): void
    {
        Request::setCurrent(Request::make('GET', '/', [], [], ['Accept' => 'application/json']));

        $response = (new ErrorRenderer())->exception(new AuthorizationException('Custom deny'));

        self::assertSame(403, $response->httpStatus());
        self::assertStringContainsString('Custom deny', $response->body());
    }
}
