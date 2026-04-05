<?php

declare(strict_types=1);

namespace Vortex\Tests;

use PHPUnit\Framework\TestCase;
use Vortex\View\Factory;

final class ViewFactoryTest extends TestCase
{
    public function testAddTemplatePathResolvesTemplatesFromPackageRoot(): void
    {
        $appViews = sys_get_temp_dir() . '/vortex_vw_' . bin2hex(random_bytes(3));
        $pkgViews = sys_get_temp_dir() . '/vortex_vw_pkg_' . bin2hex(random_bytes(3));
        mkdir($appViews, 0777, true);
        mkdir($pkgViews . '/vendor/partial', 0777, true);
        file_put_contents($appViews . '/only_app.twig', 'app');
        file_put_contents($pkgViews . '/vendor/partial/foo.twig', 'from-pkg');

        $factory = new Factory($appViews, true, null);
        $factory->addTemplatePath($pkgViews);

        self::assertSame('from-pkg', $factory->render('vendor.partial.foo'));
    }
}
