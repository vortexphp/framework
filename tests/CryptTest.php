<?php

declare(strict_types=1);

namespace Vortex\Tests;

use PHPUnit\Framework\TestCase;
use Vortex\Crypto\Crypt;

final class CryptTest extends TestCase
{
    private ?string $savedEnv = null;

    private ?string $savedServer = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->savedEnv = $_ENV['APP_KEY'] ?? null;
        $this->savedServer = $_SERVER['APP_KEY'] ?? null;
        $k = 'base64:' . base64_encode(str_repeat('k', 32));
        $_ENV['APP_KEY'] = $k;
        $_SERVER['APP_KEY'] = $k;
        putenv('APP_KEY=' . $k);
    }

    protected function tearDown(): void
    {
        if ($this->savedEnv === null) {
            unset($_ENV['APP_KEY']);
        } else {
            $_ENV['APP_KEY'] = $this->savedEnv;
        }
        if ($this->savedServer === null) {
            unset($_SERVER['APP_KEY']);
        } else {
            $_SERVER['APP_KEY'] = $this->savedServer;
        }
        putenv('APP_KEY');
        parent::tearDown();
    }

    public function testHashAndVerifyRoundTrip(): void
    {
        $mac = Crypt::hash('payload');
        self::assertSame(64, strlen($mac));
        self::assertTrue(Crypt::verify('payload', $mac));
        self::assertFalse(Crypt::verify('other', $mac));
    }

    public function testHmacWithAlgorithm(): void
    {
        $mac = Crypt::hmac('x', 'sha384');
        self::assertSame(96, strlen($mac));
        self::assertTrue(Crypt::verify('x', $mac, 'sha384'));
        self::assertFalse(Crypt::verify('x', $mac, 'sha256'));
    }

    public function testRawKeyDecodesBase64Prefix(): void
    {
        $raw = Crypt::rawKey();
        self::assertSame(32, strlen($raw));
        self::assertSame(str_repeat('k', 32), $raw);
    }

    public function testMissingAppKeyThrows(): void
    {
        unset($_ENV['APP_KEY'], $_SERVER['APP_KEY']);
        putenv('APP_KEY');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('APP_KEY is not set');
        Crypt::hash('a');
    }
}
