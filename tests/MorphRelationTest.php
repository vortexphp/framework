<?php

declare(strict_types=1);

namespace Vortex\Tests;

use PHPUnit\Framework\TestCase;
use Vortex\AppContext;
use Vortex\Config\Repository;
use Vortex\Container;
use Vortex\Database\Connection;
use Vortex\Database\DatabaseManager;
use Vortex\Database\Model;
use Vortex\Database\MorphMap;
use Vortex\Database\Relation;

final class MorphRelationTest extends TestCase
{
    private string $configDir = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->configDir = sys_get_temp_dir() . '/vortex-morph-' . bin2hex(random_bytes(4));
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

        MorphCat::connection()->execute(
            'CREATE TABLE morph_cats (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)'
        );
        MorphPost::connection()->execute(
            'CREATE TABLE morph_posts (id INTEGER PRIMARY KEY AUTOINCREMENT, category_id INTEGER NOT NULL, title TEXT NOT NULL)'
        );
        MorphArticle::connection()->execute(
            'CREATE TABLE morph_articles (id INTEGER PRIMARY KEY AUTOINCREMENT, category_id INTEGER NOT NULL, title TEXT NOT NULL)'
        );
        MorphAsset::connection()->execute(
            'CREATE TABLE morph_assets (id INTEGER PRIMARY KEY AUTOINCREMENT, attachable_type TEXT NOT NULL, attachable_id INTEGER NOT NULL, label TEXT NOT NULL)'
        );
        MorphPhoto::connection()->execute(
            'CREATE TABLE morph_photos (id INTEGER PRIMARY KEY AUTOINCREMENT, imageable_type TEXT NOT NULL, imageable_id INTEGER NOT NULL, path TEXT NOT NULL)'
        );
        MorphUser::connection()->execute(
            'CREATE TABLE morph_users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)'
        );
        MorphAvatar::connection()->execute(
            'CREATE TABLE morph_avatars (id INTEGER PRIMARY KEY AUTOINCREMENT, owner_type TEXT NOT NULL, owner_id INTEGER NOT NULL, url TEXT NOT NULL)'
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

    public function testMorphToLazyAndEager(): void
    {
        $cat = MorphCat::create(['name' => 'News']);
        $post = MorphPost::create(['category_id' => (int) $cat->id, 'title' => 'Hello']);
        $asset = MorphAsset::create([
            'attachable_type' => MorphPost::class,
            'attachable_id' => (int) $post->id,
            'label' => 'hero.png',
        ]);

        $fresh = MorphAsset::find((int) $asset->id);
        self::assertNotNull($fresh);
        $p = $fresh->attachable();
        self::assertInstanceOf(MorphPost::class, $p);
        self::assertSame('Hello', $p->title);

        $loaded = MorphAsset::query()->with(['attachable'])->first();
        self::assertNotNull($loaded);
        self::assertInstanceOf(MorphPost::class, $loaded->attachable);
        self::assertSame('Hello', $loaded->attachable->title);
    }

    public function testMorphToEagerNestedGroupsByConcreteClass(): void
    {
        $cat = MorphCat::create(['name' => 'X']);
        $post = MorphPost::create(['category_id' => (int) $cat->id, 'title' => 'P']);
        $article = MorphArticle::create(['category_id' => (int) $cat->id, 'title' => 'A']);
        MorphAsset::create([
            'attachable_type' => MorphPost::class,
            'attachable_id' => (int) $post->id,
            'label' => 'a1',
        ]);
        MorphAsset::create([
            'attachable_type' => MorphArticle::class,
            'attachable_id' => (int) $article->id,
            'label' => 'a2',
        ]);

        $assets = MorphAsset::query()->with(['attachable.category'])->orderBy('id')->get();
        self::assertCount(2, $assets);
        self::assertInstanceOf(MorphPost::class, $assets[0]->attachable);
        self::assertInstanceOf(MorphArticle::class, $assets[1]->attachable);
        self::assertInstanceOf(MorphCat::class, $assets[0]->attachable->category);
        self::assertInstanceOf(MorphCat::class, $assets[1]->attachable->category);
        self::assertSame('X', $assets[0]->attachable->category->name);
    }

    public function testMorphManyLazyAndEager(): void
    {
        $cat = MorphCat::create(['name' => 'C']);
        $post = MorphPost::create(['category_id' => (int) $cat->id, 'title' => 'P']);
        MorphPhoto::create(['imageable_type' => MorphPost::class, 'imageable_id' => (int) $post->id, 'path' => '/a.jpg']);
        MorphPhoto::create(['imageable_type' => MorphPost::class, 'imageable_id' => (int) $post->id, 'path' => '/b.jpg']);

        $fresh = MorphPost::find((int) $post->id);
        self::assertNotNull($fresh);
        $photos = $fresh->photos();
        self::assertCount(2, $photos);
        self::assertSame('/a.jpg', $photos[0]->path);
        self::assertSame('/b.jpg', $photos[1]->path);

        $with = MorphPost::query()->with(['photos'])->first();
        self::assertNotNull($with);
        self::assertCount(2, $with->photos);
    }

    public function testMorphOneKeepsFirstById(): void
    {
        $user = MorphUser::create(['name' => 'Ann']);
        MorphAvatar::create(['owner_type' => MorphUser::class, 'owner_id' => (int) $user->id, 'url' => '/old']);
        MorphAvatar::create(['owner_type' => MorphUser::class, 'owner_id' => (int) $user->id, 'url' => '/new']);

        $u = MorphUser::query()->with(['avatar'])->first();
        self::assertNotNull($u);
        self::assertInstanceOf(MorphAvatar::class, $u->avatar);
        self::assertSame('/old', $u->avatar->url);

        $u2 = MorphUser::find((int) $user->id);
        self::assertNotNull($u2);
        $one = $u2->avatar();
        self::assertNotNull($one);
        self::assertSame('/old', $one->url);
    }

    private function clearAppContext(): void
    {
        $ref = new \ReflectionClass(AppContext::class);
        $prop = $ref->getProperty('container');
        $prop->setAccessible(true);
        $prop->setValue(null, null);
    }
}

final class MorphCat extends Model
{
    protected static ?string $table = 'morph_cats';

    /** @var list<string> */
    protected static array $fillable = ['name'];

    protected static bool $timestamps = false;
}

final class MorphPost extends Model
{
    protected static ?string $table = 'morph_posts';

    /** @var list<string> */
    protected static array $fillable = ['category_id', 'title'];

    protected static bool $timestamps = false;

    protected static function eagerRelations(): array
    {
        return [
            'category' => Relation::belongsTo(MorphCat::class, 'category_id'),
            'photos' => Relation::morphMany(MorphPhoto::class, 'imageable'),
        ];
    }

    /** @return list<MorphPhoto> */
    public function photos(): array
    {
        return $this->morphMany(MorphPhoto::class, 'imageable');
    }

    public function category(): ?MorphCat
    {
        return $this->belongsTo(MorphCat::class, 'category_id');
    }
}

final class MorphArticle extends Model
{
    protected static ?string $table = 'morph_articles';

    /** @var list<string> */
    protected static array $fillable = ['category_id', 'title'];

    protected static bool $timestamps = false;

    protected static function eagerRelations(): array
    {
        return [
            'category' => Relation::belongsTo(MorphCat::class, 'category_id'),
        ];
    }

    public function category(): ?MorphCat
    {
        return $this->belongsTo(MorphCat::class, 'category_id');
    }
}

final class MorphAsset extends Model
{
    protected static ?string $table = 'morph_assets';

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

final class MorphPhoto extends Model
{
    protected static ?string $table = 'morph_photos';

    /** @var list<string> */
    protected static array $fillable = ['imageable_type', 'imageable_id', 'path'];

    protected static bool $timestamps = false;
}

final class MorphUser extends Model
{
    protected static ?string $table = 'morph_users';

    /** @var list<string> */
    protected static array $fillable = ['name'];

    protected static bool $timestamps = false;

    protected static function eagerRelations(): array
    {
        return [
            'avatar' => Relation::morphOne(MorphAvatar::class, 'owner'),
        ];
    }

    public function avatar(): ?MorphAvatar
    {
        return $this->morphOne(MorphAvatar::class, 'owner');
    }
}

final class MorphAvatar extends Model
{
    protected static ?string $table = 'morph_avatars';

    /** @var list<string> */
    protected static array $fillable = ['owner_type', 'owner_id', 'url'];

    protected static bool $timestamps = false;
}
