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
        $out = Stub::render('command', ['CLASS' => 'DemoCommand']);
        self::assertStringContainsString('final class DemoCommand extends Command', $out);
        self::assertStringNotContainsString('{{CLASS}}', $out);
    }

    public function testMissingStubThrows(): void
    {
        $this->expectException(RuntimeException::class);
        Stub::render('definitely_missing_stub_xyz', []);
    }
}
