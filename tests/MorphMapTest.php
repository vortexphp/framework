<?php

declare(strict_types=1);

namespace Vortex\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Vortex\AppContext;
use Vortex\Config\Repository;
use Vortex\Container;
use Vortex\Database\Connection;
use Vortex\Database\DatabaseManager;
use Vortex\Database\Model;
use Vortex\Database\MorphMap;
use Vortex\Database\Relation;

final class MorphMapTest extends TestCase
{
    private string $configDir = '';

    protected function setUp(): void
    {
        parent::setUp();
        MorphMap::clearForTesting();

        $this->configDir = sys_get_temp_dir() . '/vortex-morphmap-' . bin2hex(random_bytes(4));
        mkdir($this->configDir, 0700, true);
        file_put_contents(
            $this->configDir . '/database.php',
            <<<'PHP'
<?php
return [
    'default' => 'default',
    'connections' => [
        'default' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'host' => '127.0.0.1',
            'port' => '3306',
            'username' => '',
            'password' => '',
        ],
    ],
];
PHP
            ,
        );
        Repository::setInstance(new Repository($this->configDir));

        $container = new Container();
        $container->instance(Container::class, $container);
        $container->singleton(DatabaseManager::class, static fn (): DatabaseManager => DatabaseManager::fromRepository());
        $container->singleton(Connection::class, static fn (Container $c): Connection => $c->make(DatabaseManager::class)->connection());
        AppContext::set($container);

        MapPost::connection()->execute(
            'CREATE TABLE map_mm_posts (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL)'
        );
        MapPhoto::connection()->execute(
            'CREATE TABLE map_mm_photos (id INTEGER PRIMARY KEY AUTOINCREMENT, imageable_type TEXT NOT NULL, imageable_id INTEGER NOT NULL, path TEXT NOT NULL)'
        );
        MapArticle::connection()->execute(
            'CREATE TABLE map_mm_articles (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL)'
        );
        MorphMapAsset::connection()->execute(
            'CREATE TABLE map_assets (id INTEGER PRIMARY KEY AUTOINCREMENT, attachable_type TEXT NOT NULL, attachable_id INTEGER NOT NULL, label TEXT NOT NULL)'
        );
    }

    protected function tearDown(): void
    {
        MorphMap::clearForTesting();
        $this->clearAppContext();
        Repository::forgetInstance();
        if ($this->configDir !== '' && is_file($this->configDir . '/database.php')) {
            unlink($this->configDir . '/database.php');
            rmdir($this->configDir);
        }
        parent::tearDown();
    }

    public function testGetMorphClassUsesAliasWhenRegistered(): void
    {
        MorphMap::register(['xpost' => MapPost::class]);
        self::assertSame('xpost', MapPost::getMorphClass());
        self::assertSame(MapPost::class, MorphMap::resolveClass('xpost'));
    }

    public function testMorphManyQueryUsesAliasInTypeColumn(): void
    {
        MorphMap::register(['xpost' => MapPost::class]);
        $post = MapPost::create(['title' => 'Hi']);
        MapPhoto::create([
            'imageable_type' => 'xpost',
            'imageable_id' => (int) $post->id,
            'path' => '/1.jpg',
        ]);
        $photos = $post->photos();
        self::assertCount(1, $photos);
        self::assertSame('/1.jpg', $photos[0]->path);
    }

    public function testMorphToResolvesAlias(): void
    {
        MorphMap::register(['xpost' => MapPost::class]);
        $post = MapPost::create(['title' => 'A']);
        $asset = MorphMapAsset::create([
            'attachable_type' => 'xpost',
            'attachable_id' => (int) $post->id,
            'label' => 'f',
        ]);
        $fresh = MorphMapAsset::find((int) $asset->id);
        self::assertNotNull($fresh);
        $p = $fresh->attachable();
        self::assertInstanceOf(MapPost::class, $p);
        self::assertSame('A', $p->title);
    }

    public function testEagerMorphWithAliases(): void
    {
        MorphMap::register([
            'xpost' => MapPost::class,
            'xarticle' => MapArticle::class,
        ]);
        $post = MapPost::create(['title' => 'P']);
        $article = MapArticle::create(['title' => 'Ar']);
        MorphMapAsset::create(['attachable_type' => 'xpost', 'attachable_id' => (int) $post->id, 'label' => 'a1']);
        MorphMapAsset::create(['attachable_type' => 'xarticle', 'attachable_id' => (int) $article->id, 'label' => 'a2']);

        $assets = MorphMapAsset::query()->with(['attachable'])->orderBy('id')->get();
        self::assertInstanceOf(MapPost::class, $assets[0]->attachable);
        self::assertInstanceOf(MapArticle::class, $assets[1]->attachable);
    }

    public function testRegisterRejectsNonModel(): void
    {
        $this->expectException(InvalidArgumentException::class);
        MorphMap::register(['bad' => \stdClass::class]);
    }

    private function clearAppContext(): void
    {
        $ref = new \ReflectionClass(AppContext::class);
        $prop = $ref->getProperty('container');
        $prop->setAccessible(true);
        $prop->setValue(null, null);
    }
}

final class MapPost extends Model
{
    protected static ?string $table = 'map_mm_posts';

    /** @var list<string> */
    protected static array $fillable = ['title'];

    protected static bool $timestamps = false;

    protected static function eagerRelations(): array
    {
        return [
            'photos' => Relation::morphMany(MapPhoto::class, 'imageable'),
        ];
    }

    /** @return list<MapPhoto> */
    public function photos(): array
    {
        return $this->morphMany(MapPhoto::class, 'imageable');
    }
}

final class MapArticle extends Model
{
    protected static ?string $table = 'map_mm_articles';

    /** @var list<string> */
    protected static array $fillable = ['title'];

    protected static bool $timestamps = false;
}

final class MapPhoto extends Model
{
    protected static ?string $table = 'map_mm_photos';

    /** @var list<string> */
    protected static array $fillable = ['imageable_type', 'imageable_id', 'path'];

    protected static bool $timestamps = false;
}

final class MorphMapAsset extends Model
{
    protected static ?string $table = 'map_assets';

    /** @var list<string> */
    protected static array $fillable = ['attachable_type', 'attachable_id', 'label'];

    protected static bool $timestamps = false;

    protected static function eagerRelations(): array
    {
        return [
            'attachable' => Relation::morphTo('attachable'),
        ];
    }

    public function attachable(): ?Model
    {
        return $this->morphTo('attachable');
    }
}
