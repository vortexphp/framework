<?php

declare(strict_types=1);

namespace Vortex\Tests;

use PHPUnit\Framework\TestCase;
use Vortex\Mail\LogMailer;
use Vortex\Mail\MailMessage;

final class LogMailerTest extends TestCase
{
    private string $base = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->base = sys_get_temp_dir() . '/vortex -mail-' . bin2hex(random_bytes(4));
        mkdir($this->base, 0700, true);
    }

    protected function tearDown(): void
    {
        $log = $this->base . '/storage/logs/mail.log';
        if (is_file($log)) {
            unlink($log);
        }
        if (is_dir($this->base . '/storage/logs')) {
            rmdir($this->base . '/storage/logs');
        }
        if (is_dir($this->base . '/storage')) {
            rmdir($this->base . '/storage');
        }
        if (is_dir($this->base)) {
            rmdir($this->base);
        }
        parent::tearDown();
    }

    public function testWritesMailLog(): void
    {
        $m = new LogMailer($this->base);
        $m->send(new MailMessage(
            ['from@test.dev', 'F'],
            [['to@test.dev']],
            'Hello',
            "Body\n",
        ));
        $path = $this->base . '/storage/logs/mail.log';
        self::assertFileExists($path);
        $c = (string) file_get_contents($path);
        self::assertStringContainsString('to@test.dev', $c);
        self::assertStringContainsString('Hello', $c);
        self::assertStringContainsString('Body', $c);
    }
}
