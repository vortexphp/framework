<?php

declare(strict_types=1);

namespace Vortex\Tests;

use Mockery;
use PHPUnit\Framework\TestCase;
use Vortex\AppContext;
use Vortex\Cache\Cache;
use Vortex\Cache\CacheManager;
use Vortex\Container;
use Vortex\Contracts\Cache as CacheContract;
use Vortex\Database\Connection;
use Vortex\Database\DB;

final class MockeryIntegrationTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = new Container();
        $this->container->instance(Container::class, $this->container);
        AppContext::set($this->container);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        $this->clearAppContext();
        parent::tearDown();
    }

    public function testDbFacadeCanUseMockeryConnectionMock(): void
    {
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('select')
            ->once()
            ->with('SELECT 1', [])
            ->andReturn([['v' => 1]]);
        $this->container->instance(Connection::class, $connection);

        self::assertSame([['v' => 1]], DB::select('SELECT 1'));
    }

    public function testCacheFacadeCanUseMockeryManagerAndStoreMocks(): void
    {
        $store = Mockery::mock(CacheContract::class);
        $store->shouldReceive('get')
            ->once()
            ->with('answer', 'fallback')
            ->andReturn(42);

        $manager = Mockery::mock(CacheManager::class);
        $manager->shouldReceive('store')
            ->once()
            ->with(null)
            ->andReturn($store);
        $this->container->instance(CacheManager::class, $manager);

        self::assertSame(42, Cache::get('answer', 'fallback'));
    }

    private function clearAppContext(): void
    {
        $ref = new \ReflectionClass(AppContext::class);
        $prop = $ref->getProperty('container');
        $prop->setAccessible(true);
        $prop->setValue(null, null);
    }
}
