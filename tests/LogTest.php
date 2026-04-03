<?php

declare(strict_types=1);

namespace Vortex\Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Vortex\Support\Log;

final class LogTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/vortex-log-test-' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/storage/logs', 0777, true);
        Log::setBasePath($this->dir);
    }

    protected function tearDown(): void
    {
        Log::reset();
        $log = $this->dir . '/storage/logs/app.log';
        if (is_file($log)) {
            unlink($log);
        }
        if (is_dir($this->dir . '/storage/logs')) {
            rmdir($this->dir . '/storage/logs');
        }
        if (is_dir($this->dir . '/storage')) {
            rmdir($this->dir . '/storage');
        }
        if (is_dir($this->dir)) {
            rmdir($this->dir);
        }
        parent::tearDown();
    }

    public function testWriteWithoutBasePathThrows(): void
    {
        Log::reset();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('setBasePath');

        Log::info('x');
    }

    public function testInfoAppendsLine(): void
    {
        Log::info('hello', ['user_id' => 1]);

        $content = file_get_contents($this->dir . '/storage/logs/app.log');
        self::assertStringContainsString('] INFO hello ', $content);
        self::assertStringContainsString('"user_id":1', $content);
    }

    public function testExceptionFormat(): void
    {
        Log::exception(new RuntimeException('boom'));

        $content = file_get_contents($this->dir . '/storage/logs/app.log');
        self::assertStringContainsString('EXCEPTION RuntimeException: boom', $content);
    }
}
