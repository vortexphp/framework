<?php

declare(strict_types=1);

namespace Vortex\Tests;

use PHPUnit\Framework\TestCase;
use Vortex\I18n\Translator;

final class TranslatorTest extends TestCase
{
    protected function tearDown(): void
    {
        Translator::forgetInstance();
        parent::tearDown();
    }

    public function testDotKeysAndPlaceholders(): void
    {
        $dir = sys_get_temp_dir() . '/pc-lang-' . bin2hex(random_bytes(4));
        mkdir($dir, 0700, true);
        file_put_contents(
            $dir . '/en.php',
            "<?php\nreturn ['greet' => ['hi' => 'Hello :name'], 'only' => 'x'];\n",
        );

        try {
            $t = new Translator($dir, 'en', 'en', ['en']);
            Translator::setInstance($t);
            self::assertSame('Hello Ada', Translator::get('greet.hi', ['name' => 'Ada']));
        } finally {
            unlink($dir . '/en.php');
            rmdir($dir);
        }
    }

    public function testFallsBackWhenKeyMissing(): void
    {
        $dir = sys_get_temp_dir() . '/pc-lang-' . bin2hex(random_bytes(4));
        mkdir($dir, 0700, true);
        file_put_contents($dir . '/en.php', "<?php\nreturn ['a' => ['b' => 'EN']];\n");
        file_put_contents($dir . '/fr.php', "<?php\nreturn [];\n");

        try {
            $t = new Translator($dir, 'fr', 'en', ['en', 'fr']);
            Translator::setInstance($t);
            self::assertSame('EN', Translator::get('a.b'));
        } finally {
            unlink($dir . '/en.php');
            unlink($dir . '/fr.php');
            rmdir($dir);
        }
    }

    public function testSetLocaleRejectsUnknown(): void
    {
        $dir = sys_get_temp_dir() . '/pc-lang-' . bin2hex(random_bytes(4));
        mkdir($dir, 0700, true);
        file_put_contents($dir . '/en.php', "<?php\nreturn ['k' => 'v'];\n");

        try {
            $t = new Translator($dir, 'en', 'en', ['en']);
            Translator::setInstance($t);
            Translator::setLocale('xx');
            self::assertSame('en', Translator::locale());
        } finally {
            unlink($dir . '/en.php');
            rmdir($dir);
        }
    }
}
