<?php

declare(strict_types=1);

namespace Vortex\Tests;

use PHPUnit\Framework\TestCase;
use Vortex\Config\Repository;

final class ConfigRepositoryTest extends TestCase
{
    protected function tearDown(): void
    {
        Repository::forgetInstance();
        parent::tearDown();
    }

    public function testLoadsPhpFilesAndDotPaths(): void
    {
        $dir = sys_get_temp_dir() . '/pc-repo-' . bin2hex(random_bytes(4));
        mkdir($dir, 0700, true);
        file_put_contents(
            $dir . '/app.php',
            "<?php\nreturn ['name' => 't', 'db' => ['host' => 'h']];\n",
        );

        try {
            $repo = new Repository($dir);
            Repository::setInstance($repo);
            self::assertSame('t', Repository::get('app.name'));
            self::assertSame('h', Repository::get('app.db.host'));
            self::assertSame('x', Repository::get('app.missing', 'x'));
            self::assertTrue(Repository::has('app.name'));
            self::assertFalse(Repository::has('app.missing'));
        } finally {
            unlink($dir . '/app.php');
            rmdir($dir);
        }
    }

    public function testMissingDirectoryYieldsEmptyDefaults(): void
    {
        $repo = new Repository(sys_get_temp_dir() . '/no-such-config-repo-' . bin2hex(random_bytes(4)));
        Repository::setInstance($repo);

        self::assertNull(Repository::get('anything'));
    }
}
