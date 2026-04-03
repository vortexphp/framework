<?php

declare(strict_types=1);

namespace Vortex\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Vortex\Http\NativeSessionStore;
use Vortex\Http\NullSessionStore;
use Vortex\Http\SessionManager;

final class SessionManagerTest extends TestCase
{
    public function testFromInstances(): void
    {
        $null = new NullSessionStore();
        $mgr = SessionManager::fromInstances('null', ['null' => $null]);
        self::assertSame($null, $mgr->store());
        self::assertSame($null, $mgr->store('null'));
    }

    public function testFromConfigResolvesDrivers(): void
    {
        $mgr = SessionManager::fromConfig([
            'default' => 'null',
            'stores' => [
                'null' => ['driver' => 'null'],
                'native' => ['driver' => 'native', 'name' => 'vortex_session'],
            ],
        ]);

        self::assertInstanceOf(NullSessionStore::class, $mgr->store('null'));
        self::assertInstanceOf(NativeSessionStore::class, $mgr->store('native'));
    }

    public function testUnknownDriverThrows(): void
    {
        $mgr = SessionManager::fromConfig([
            'default' => 'x',
            'stores' => [
                'x' => ['driver' => 'redis'],
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown session driver');
        $mgr->store('x');
    }

    public function testMissingStoresConfigThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No session stores configured.');
        SessionManager::fromConfig([
            'default' => 'native',
        ]);
    }
}
