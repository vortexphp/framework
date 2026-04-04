<?php

declare(strict_types=1);

namespace Vortex\Tests;

use PHPUnit\Framework\TestCase;
use Vortex\Testing\KernelBrowser;

final class KernelBrowserTest extends TestCase
{
    private string $fixtureBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixtureBase = __DIR__ . '/Fixtures/minimal-http-app';
    }

    protected function tearDown(): void
    {
        KernelBrowser::resetRequestContext();
        parent::tearDown();
    }

    public function testGetDispatchesThroughKernel(): void
    {
        $browser = KernelBrowser::boot($this->fixtureBase);
        $response = $browser->get('/t');
        self::assertSame(200, $response->httpStatus());
        self::assertSame('ok', $response->body());
    }

    public function testRequestHelperPassesQueryAndHeaders(): void
    {
        $browser = KernelBrowser::boot($this->fixtureBase);
        $response = $browser->request('GET', '/t', [
            'query' => ['x' => '1'],
            'headers' => ['X-Test' => 'y'],
        ]);
        self::assertSame(200, $response->httpStatus());
    }

    public function testDecodeJson(): void
    {
        $browser = KernelBrowser::boot($this->fixtureBase);
        $response = $browser->postJson('/echo-json', ['a' => 1]);
        self::assertSame(200, $response->httpStatus());
        self::assertSame(['a' => 1, 'method' => 'POST'], KernelBrowser::decodeJson($response));
    }
}
