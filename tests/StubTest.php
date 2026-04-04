<?php

declare(strict_types=1);

namespace Vortex\Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Vortex\Console\Stub;

final class StubTest extends TestCase
{
    public function testRenderSubstitutesPlaceholders(): void
    {
        $out = Stub::render('command', [
            'NAMESPACE' => 'App\\Commands',
            'CLASS' => 'DemoCommand',
        ]);
        self::assertStringContainsString('namespace App\\Commands', $out);
        self::assertStringContainsString('final class DemoCommand extends Command', $out);
        self::assertStringNotContainsString('{{CLASS}}', $out);

        $ctrl = Stub::render('controller', [
            'NAMESPACE' => 'App\\Controllers',
            'CLASS' => 'DemoController',
        ]);
        self::assertStringContainsString('namespace App\\Controllers', $ctrl);
        self::assertStringContainsString('final class DemoController extends Controller', $ctrl);
    }

    public function testMissingStubThrows(): void
    {
        $this->expectException(RuntimeException::class);
        Stub::render('definitely_missing_stub_xyz', []);
    }
}
