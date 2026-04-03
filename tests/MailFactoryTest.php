<?php

declare(strict_types=1);

namespace Vortex\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Vortex\Config\Repository;
use Vortex\Mail\LogMailer;
use Vortex\Mail\MailFactory;
use Vortex\Mail\NullMailer;
use Vortex\Mail\SmtpMailer;

final class MailFactoryTest extends TestCase
{
    private string $configDir = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->configDir = sys_get_temp_dir() . '/pc-mail-factory-' . bin2hex(random_bytes(4));
        mkdir($this->configDir, 0700, true);
    }

    protected function tearDown(): void
    {
        Repository::forgetInstance();
        if ($this->configDir !== '' && is_dir($this->configDir)) {
            foreach (glob($this->configDir . '/*.php') ?: [] as $f) {
                unlink($f);
            }
            rmdir($this->configDir);
        }
        parent::tearDown();
    }

    private function writeMailPhp(string $body): void
    {
        file_put_contents($this->configDir . '/mail.php', $body);
    }

    public function testLogDriver(): void
    {
        $this->writeMailPhp("<?php\nreturn ['driver' => 'log', 'from' => ['address'=>'a@b','name'=>'n'], 'smtp'=>[]];\n");
        Repository::setInstance(new Repository($this->configDir));
        self::assertInstanceOf(LogMailer::class, MailFactory::make('/tmp'));
    }

    public function testNullDriver(): void
    {
        $this->writeMailPhp("<?php\nreturn ['driver' => 'null', 'from' => ['address'=>'a@b','name'=>'n'], 'smtp'=>[]];\n");
        Repository::setInstance(new Repository($this->configDir));
        self::assertInstanceOf(NullMailer::class, MailFactory::make('/tmp'));
    }

    public function testSmtpDriver(): void
    {
        $this->writeMailPhp("<?php\nreturn ['driver' => 'smtp', 'from' => ['address'=>'a@b','name'=>'n'], 'smtp' => ['host'=>'h','port'=>587,'username'=>'u','password'=>'p','encryption'=>'tls']];\n");
        Repository::setInstance(new Repository($this->configDir));
        self::assertInstanceOf(SmtpMailer::class, MailFactory::make('/tmp'));
    }

    public function testUnknownDriver(): void
    {
        $this->writeMailPhp("<?php\nreturn ['driver' => 'ses', 'from' => ['address'=>'a@b','name'=>'n'], 'smtp'=>[]];\n");
        Repository::setInstance(new Repository($this->configDir));
        $this->expectException(InvalidArgumentException::class);
        MailFactory::make('/tmp');
    }
}
