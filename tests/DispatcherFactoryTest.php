<?php

declare(strict_types=1);

namespace Vortex\Tests;

use PHPUnit\Framework\TestCase;
use Vortex\Config\Repository;
use Vortex\Container;
use Vortex\Events\DispatcherFactory;
use Vortex\Tests\Fixtures\DemoEvent;
use Vortex\Tests\Fixtures\DemoListenerHandle;

final class DispatcherFactoryTest extends TestCase
{
    private string $configDir = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->configDir = sys_get_temp_dir() . '/vortex -events-' . bin2hex(random_bytes(4));
        mkdir($this->configDir, 0700, true);
    }

    protected function tearDown(): void
    {
        Repository::forgetInstance();
        DemoListenerHandle::reset();
        if ($this->configDir !== '' && is_dir($this->configDir)) {
            foreach (glob($this->configDir . '/*.php') ?: [] as $f) {
                unlink($f);
            }
            rmdir($this->configDir);
        }
        parent::tearDown();
    }

    public function testLoadsListenMapFromConfig(): void
    {
        $eventFqcn = str_replace('\\', '\\\\', DemoEvent::class);
        $listenerFqcn = str_replace('\\', '\\\\', DemoListenerHandle::class);
        file_put_contents(
            $this->configDir . '/events.php',
            <<<PHP
<?php
return [
    'listen' => [
        '{$eventFqcn}' => ['{$listenerFqcn}'],
    ],
];
PHP
            ,
        );
        Repository::setInstance(new Repository($this->configDir));
        $c = new Container();
        $c->instance(Container::class, $c);
        $d = DispatcherFactory::make($c);
        $d->dispatch(new DemoEvent('cfg'));
        self::assertSame(['handle:cfg'], DemoListenerHandle::$log);
    }
}
