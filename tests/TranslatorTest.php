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
        $dir = sys_get_temp_dir() . '/vortex -lang-' . bin2hex(random_bytes(4));
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
        $dir = sys_get_temp_dir() . '/vortex -lang-' . bin2hex(random_bytes(4));
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

    public function testLoadsLocaleDirectoryPhpFilesMergedWithSingleFile(): void
    {
        $dir = sys_get_temp_dir() . '/vortex-lang-sub-' . bin2hex(random_bytes(4));
        mkdir($dir . '/en', 0700, true);
        file_put_contents(
            $dir . '/en.php',
            "<?php\nreturn ['root' => 'from-single', 'shared' => ['a' => 'one']];\n",
        );
        file_put_contents(
            $dir . '/en/app.php',
            "<?php\nreturn ['nested' => ['k' => 'from-app'], 'shared' => ['b' => 'two']];\n",
        );

        try {
            $t = new Translator($dir, 'en', 'en', ['en']);
            Translator::setInstance($t);
            self::assertSame('from-single', Translator::get('root'));
            self::assertSame('from-app', Translator::get('nested.k'));
            self::assertSame('one', Translator::get('shared.a'));
            self::assertSame('two', Translator::get('shared.b'));
        } finally {
            unlink($dir . '/en.php');
            unlink($dir . '/en/app.php');
            rmdir($dir . '/en');
            rmdir($dir);
        }
    }

    public function testLoadsOnlyLocaleSubdirectoryWhenNoSingleFile(): void
    {
        $dir = sys_get_temp_dir() . '/vortex-lang-dir-' . bin2hex(random_bytes(4));
        mkdir($dir . '/en', 0700, true);
        file_put_contents(
            $dir . '/en/messages.php',
            "<?php\nreturn ['home' => ['title' => 'Hi']];\n",
        );

        try {
            $t = new Translator($dir, 'en', 'en', ['en']);
            Translator::setInstance($t);
            self::assertSame('Hi', Translator::get('home.title'));
        } finally {
            unlink($dir . '/en/messages.php');
            rmdir($dir . '/en');
            rmdir($dir);
        }
    }

    public function testSetLocaleRejectsUnknown(): void
    {
        $dir = sys_get_temp_dir() . '/vortex -lang-' . bin2hex(random_bytes(4));
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
