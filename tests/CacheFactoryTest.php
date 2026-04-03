<?php

declare(strict_types=1);

namespace Vortex\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Vortex\Cache\CacheFactory;
use Vortex\Cache\FileCache;
use Vortex\Cache\NullCache;
use Vortex\Config\Repository;

final class CacheFactoryTest extends TestCase
{
    private string $configDir = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->configDir = sys_get_temp_dir() . '/vortex -cache-factory-' . bin2hex(random_bytes(4));
        mkdir($this->configDir, 0700, true);
    }

    protected function tearDown(): void
    {
        Repository::forgetInstance();
        if ($this->configDir !== '' && is_dir($this->configDir)) {
            foreach (glob($this->configDir . '/*.php') ?: [] as $f) {
                unlink($f);
            }
            rmdir($this->configDir);
        }
        parent::tearDown();
    }

    private function writeCachePhp(string $contents): void
    {
        file_put_contents($this->configDir . '/cache.php', $contents);
    }

    public function testNullDriver(): void
    {
        $this->writeCachePhp("<?php\nreturn ['driver' => 'null', 'path' => '/tmp/x', 'prefix' => ''];");
        Repository::setInstance(new Repository($this->configDir));
        self::assertInstanceOf(NullCache::class, CacheFactory::make('/tmp'));
    }

    public function testFileDriver(): void
    {
        $path = sys_get_temp_dir() . '/vortex -cf-data-' . bin2hex(random_bytes(4));
        $this->writeCachePhp(
            "<?php\nreturn ['driver' => 'file', 'path' => " . var_export($path, true) . ", 'prefix' => 'x:'];",
        );
        Repository::setInstance(new Repository($this->configDir));
        try {
            $cache = CacheFactory::make('/tmp');
            self::assertInstanceOf(FileCache::class, $cache);
        } finally {
            if (is_dir($path)) {
                foreach (glob($path . '/*') ?: [] as $f) {
                    if (is_file($f)) {
                        unlink($f);
                    }
                }
                @rmdir($path);
            }
        }
    }

    public function testUnknownLegacyDriverThrows(): void
    {
        $this->writeCachePhp("<?php\nreturn ['driver' => 'redis', 'path' => '/tmp', 'prefix' => ''];");
        Repository::setInstance(new Repository($this->configDir));
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported cache driver');
        CacheFactory::make('/tmp');
    }
}
