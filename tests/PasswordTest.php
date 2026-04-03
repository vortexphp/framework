<?php

declare(strict_types=1);

namespace Vortex\Tests;

use PHPUnit\Framework\TestCase;
use Vortex\Crypto\Password;
use Vortex\Crypto\SecurityHelp;

final class PasswordTest extends TestCase
{
    public function testHashVerifyRoundTrip(): void
    {
        $hash = Password::hash('secret-value');
        self::assertNotSame('secret-value', $hash);
        self::assertTrue(Password::verify('secret-value', $hash));
        self::assertFalse(Password::verify('other', $hash));
    }

    public function testVerifyRejectsEmptyHash(): void
    {
        self::assertFalse(Password::verify('x', ''));
    }

    public function testNamespaceGuideIsNonEmpty(): void
    {
        $lines = SecurityHelp::namespaceGuide();
        self::assertNotSame([], $lines);
        self::assertStringContainsString('Password', implode(' ', $lines));
        self::assertStringContainsString('Crypt', implode(' ', $lines));
    }
}
