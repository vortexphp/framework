<?php

declare(strict_types=1);

namespace Vortex\Tests;

use PHPUnit\Framework\TestCase;
use Vortex\Http\TrustProxies;

final class TrustProxiesTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $serverSnapshot = [];

    private ?string $trustedEnvBefore = null;

    protected function setUp(): void
    {
        $this->serverSnapshot = $_SERVER;
        $this->trustedEnvBefore = $_ENV['TRUSTED_PROXIES'] ?? null;
        unset($_ENV['TRUSTED_PROXIES'], $_SERVER['TRUSTED_PROXIES']);
        putenv('TRUSTED_PROXIES');
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverSnapshot;
        if ($this->trustedEnvBefore === null) {
            unset($_ENV['TRUSTED_PROXIES']);
            putenv('TRUSTED_PROXIES');
        } else {
            $_ENV['TRUSTED_PROXIES'] = $this->trustedEnvBefore;
            putenv('TRUSTED_PROXIES=' . $this->trustedEnvBefore);
        }
    }

    public function testTrustedProxyAppliesForwardedProtoHostPort(): void
    {
        $_ENV['TRUSTED_PROXIES'] = '10.0.0.1';
        $_SERVER = array_merge($this->serverSnapshot, [
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_X_FORWARDED_PROTO' => 'https, http',
            'HTTP_X_FORWARDED_HOST' => 'api.example.com, other.internal',
            'HTTP_X_FORWARDED_PORT' => '443, 80',
        ]);
        unset($_SERVER['HTTPS']);

        TrustProxies::apply();

        self::assertSame('on', $_SERVER['HTTPS'] ?? null);
        self::assertSame('api.example.com', $_SERVER['HTTP_HOST'] ?? null);
        self::assertSame('api.example.com', $_SERVER['SERVER_NAME'] ?? null);
        self::assertSame('443', $_SERVER['SERVER_PORT'] ?? null);
    }

    public function testUntrustedRemoteDoesNotApply(): void
    {
        $_ENV['TRUSTED_PROXIES'] = '10.0.0.1';
        $_SERVER = array_merge($this->serverSnapshot, [
            'REMOTE_ADDR' => '203.0.113.1',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ]);
        unset($_SERVER['HTTPS']);

        TrustProxies::apply();

        self::assertArrayNotHasKey('HTTPS', $_SERVER);
    }

    public function testWildcardTrustsAnyRemote(): void
    {
        $_ENV['TRUSTED_PROXIES'] = '*';
        $_SERVER = array_merge($this->serverSnapshot, [
            'REMOTE_ADDR' => '203.0.113.9',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ]);
        unset($_SERVER['HTTPS']);

        TrustProxies::apply();

        self::assertSame('on', $_SERVER['HTTPS'] ?? null);
    }

    public function testEmptyTrustedProxiesIsNoOp(): void
    {
        $_SERVER = array_merge($this->serverSnapshot, [
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ]);
        unset($_SERVER['HTTPS'], $_ENV['TRUSTED_PROXIES']);
        putenv('TRUSTED_PROXIES');

        TrustProxies::apply();

        self::assertArrayNotHasKey('HTTPS', $_SERVER);
    }
}
