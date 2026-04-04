<?php

declare(strict_types=1);

namespace Vortex\Tests;

use PHPUnit\Framework\TestCase;
use Vortex\Auth\RememberCookie;
use Vortex\Crypto\Crypt;

final class RememberCookieTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $key = 'base64:' . base64_encode(random_bytes(32));
        putenv('APP_KEY=' . $key);
        $_ENV['APP_KEY'] = $key;
        $_SERVER['APP_KEY'] = $key;
    }

    protected function tearDown(): void
    {
        putenv('APP_KEY');
        unset($_ENV['APP_KEY'], $_SERVER['APP_KEY']);
        parent::tearDown();
    }

    public function testValidateAcceptsSignedPayload(): void
    {
        $exp = time() + 3600;
        $json = json_encode(['id' => 42, 'exp' => $exp], JSON_THROW_ON_ERROR);
        $value = base64_encode($json) . '.' . Crypt::hash($json);

        self::assertSame(42, RememberCookie::validate($value));
    }

    public function testValidateRejectsExpiredPayload(): void
    {
        $json = json_encode(['id' => 42, 'exp' => time() - 10], JSON_THROW_ON_ERROR);
        $value = base64_encode($json) . '.' . Crypt::hash($json);

        self::assertNull(RememberCookie::validate($value));
    }

    public function testValidateRejectsTamperedPayload(): void
    {
        $exp = time() + 3600;
        $json = json_encode(['id' => 42, 'exp' => $exp], JSON_THROW_ON_ERROR);
        $value = base64_encode($json) . 'bad.' . Crypt::hash($json);

        self::assertNull(RememberCookie::validate($value));
    }
}
