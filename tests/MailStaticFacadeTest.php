<?php

declare(strict_types=1);

namespace Vortex\Tests;

use PHPUnit\Framework\TestCase;
use Vortex\AppContext;
use Vortex\Config\Repository;
use Vortex\Container;
use Vortex\Contracts\Mailer;
use Vortex\Mail\LogMailer;
use Vortex\Mail\Mail;
use Vortex\Mail\MailMessage;

final class MailStaticFacadeTest extends TestCase
{
    private string $base = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->base = sys_get_temp_dir() . '/vortex -mail-bus-' . bin2hex(random_bytes(4));
        mkdir($this->base, 0700, true);
        $configDir = $this->base . '/config';
        mkdir($configDir, 0700, true);
        file_put_contents(
            $configDir . '/mail.php',
            "<?php\nreturn ['driver'=>'log','from'=>['address'=>'a@b.test','name'=>'A'],'smtp'=>[]];\n",
        );
        Repository::setInstance(new Repository($configDir));
        $c = new Container();
        $c->instance(Container::class, $c);
        $c->singleton(Mailer::class, fn (): LogMailer => new LogMailer($this->base));
        AppContext::set($c);
    }

    protected function tearDown(): void
    {
        $ref = new \ReflectionClass(AppContext::class);
        $prop = $ref->getProperty('container');
        $prop->setAccessible(true);
        $prop->setValue(null, null);
        Repository::forgetInstance();
        $log = $this->base . '/storage/logs/mail.log';
        if (is_file($log)) {
            unlink($log);
        }
        @rmdir($this->base . '/storage/logs');
        @rmdir($this->base . '/storage');
        @unlink($this->base . '/config/mail.php');
        @rmdir($this->base . '/config');
        @rmdir($this->base);
        parent::tearDown();
    }

    public function testSendAndDefaultFrom(): void
    {
        Mail::send(new MailMessage(
            Mail::defaultFrom(),
            [['x@y.test']],
            'T',
            'B',
        ));
        self::assertFileExists($this->base . '/storage/logs/mail.log');
    }
}
