<?php

declare(strict_types=1);

namespace Vortex\Tests;

use PHPUnit\Framework\TestCase;
use Vortex\Config\Repository;
use Vortex\Package\PackageRegistry;

final class PublishAssetsTest extends TestCase
{
    public function testPublishPublicAssetsCopiesIntoPublic(): void
    {
        $base = sys_get_temp_dir() . '/vortex_pub_' . bin2hex(random_bytes(4));
        self::assertTrue((bool) mkdir($base, 0777, true));

        $pkg = $base . '/fakepkg';
        mkdir($pkg . '/resources', 0777, true);
        mkdir($pkg . '/src', 0777, true);
        file_put_contents($pkg . '/composer.json', "{\"name\":\"test/fakepkg\"}\n");
        file_put_contents($pkg . '/resources/demo.js', '"ok"');
        file_put_contents(
            $pkg . '/src/AssetStubPackage.php',
            <<<'PHP'
<?php
declare(strict_types=1);
namespace Vortex\Tests\Fixtures\FakePkg;
use Vortex\Package\Package;
final class AssetStubPackage extends Package
{
    public function publicAssets(): array
    {
        return ['resources/demo.js' => 'js/demo.js'];
    }
}
PHP,
        );
        require $pkg . '/src/AssetStubPackage.php';

        mkdir($base . '/config', 0777, true);
        mkdir($base . '/public', 0777, true);
        file_put_contents(
            $base . '/config/app.php',
            "<?php\nreturn ['packages' => [\\Vortex\\Tests\\Fixtures\\FakePkg\\AssetStubPackage::class]];\n",
        );

        Repository::setInstance(new Repository($base . '/config'));
        try {
            $lines = PackageRegistry::publishPublicAssets($base);
        } finally {
            Repository::forgetInstance();
        }

        self::assertStringContainsString('published public/js/demo.js', implode("\n", $lines));
        self::assertSame('"ok"', file_get_contents($base . '/public/js/demo.js'));
    }

    public function testPackageRootContainingClassFindsComposerJson(): void
    {
        require_once __DIR__ . '/fixtures/asset_package/src/RootProbe.php';
        $root = PackageRegistry::packageRootContainingClass(\Vortex\Tests\Fixtures\FakePkg\RootProbe::class);
        self::assertSame(realpath(__DIR__ . '/fixtures/asset_package'), realpath($root));
    }
}
