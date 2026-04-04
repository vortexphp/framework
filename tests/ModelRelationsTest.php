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
use Vortex\Database\Relation;

final class ModelRelationsTest extends TestCase
{
    private string $configDir = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->configDir = sys_get_temp_dir() . '/vortex-model-rel-' . bin2hex(random_bytes(4));
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

        TestAuthor::connection()->execute('CREATE TABLE test_authors (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        TestCountry::connection()->execute('CREATE TABLE test_countries (id INTEGER PRIMARY KEY AUTOINCREMENT, code TEXT NOT NULL)');
        TestAuthor::connection()->execute('ALTER TABLE test_authors ADD COLUMN test_country_id INTEGER');
        TestArticle::connection()->execute(
            'CREATE TABLE test_articles (id INTEGER PRIMARY KEY AUTOINCREMENT, test_author_id INTEGER NOT NULL, title TEXT NOT NULL)'
        );
        BadEagerSpecArticle::connection()->execute(
            'CREATE TABLE bad_eager_articles (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL)'
        );
        TestTag::connection()->execute('CREATE TABLE test_tags (id INTEGER PRIMARY KEY AUTOINCREMENT, label TEXT NOT NULL)');
        TestArticle::connection()->execute(
            'CREATE TABLE test_article_tags (id INTEGER PRIMARY KEY AUTOINCREMENT, test_article_id INTEGER NOT NULL, test_tag_id INTEGER NOT NULL)'
        );
        TestAuthor::connection()->execute(
            'CREATE TABLE test_author_profiles (id INTEGER PRIMARY KEY AUTOINCREMENT, test_author_id INTEGER NOT NULL, bio TEXT NOT NULL)'
        );
    }

    protected function tearDown(): void
    {
        $this->clearAppContext();
        Repository::forgetInstance();
        if ($this->configDir !== '' && is_file($this->configDir . '/database.php')) {
            unlink($this->configDir . '/database.php');
            rmdir($this->configDir);
        }
        parent::tearDown();
    }

    public function testBelongsToAndHasManyRelations(): void
    {
        $author = TestAuthor::create(['name' => 'Alice']);
        $articleA = TestArticle::create(['test_author_id' => (int) $author->id, 'title' => 'First']);
        $articleB = TestArticle::create(['test_author_id' => (int) $author->id, 'title' => 'Second']);

        $resolvedAuthor = $articleA->author();
        self::assertInstanceOf(TestAuthor::class, $resolvedAuthor);
        self::assertSame('Alice', (string) ($resolvedAuthor->name ?? ''));

        $articles = $author->articles();
        self::assertCount(2, $articles);
        self::assertSame([(int) $articleA->id, (int) $articleB->id], array_map(
            static fn (TestArticle $article): int => (int) $article->id,
            $articles,
        ));
    }

    public function testBelongsToManyRelation(): void
    {
        $author = TestAuthor::create(['name' => 'Bob']);
        $article = TestArticle::create(['test_author_id' => (int) $author->id, 'title' => 'Relational']);
        $tagA = TestTag::create(['label' => 'php']);
        $tagB = TestTag::create(['label' => 'orm']);

        TestArticle::connection()->execute(
            'INSERT INTO test_article_tags (test_article_id, test_tag_id) VALUES (?, ?), (?, ?)',
            [(int) $article->id, (int) $tagA->id, (int) $article->id, (int) $tagB->id],
        );

        $tags = $article->tags();
        self::assertCount(2, $tags);
        self::assertSame(['php', 'orm'], array_map(
            static fn (TestTag $tag): string => (string) $tag->label,
            $tags,
        ));
    }

    public function testWithLoadsDeclaredRelationsAndPluckWorks(): void
    {
        $author = TestAuthor::create(['name' => 'Charlie']);
        $article = TestArticle::create(['test_author_id' => (int) $author->id, 'title' => 'Eager']);
        $tag = TestTag::create(['label' => 'with']);
        TestArticle::connection()->execute(
            'INSERT INTO test_article_tags (test_article_id, test_tag_id) VALUES (?, ?)',
            [(int) $article->id, (int) $tag->id],
        );

        $articles = TestArticle::query()
            ->orderBy('id')
            ->with(['author', 'tags'])
            ->get();

        self::assertCount(1, $articles);
        self::assertInstanceOf(TestAuthor::class, $articles[0]->author ?? null);
        self::assertSame('Charlie', (string) ($articles[0]->author->name ?? ''));
        self::assertCount(1, $articles[0]->tags ?? []);
        self::assertSame('with', (string) (($articles[0]->tags[0]->label) ?? ''));

        $titles = TestArticle::query()->orderBy('id')->pluck('title');
        self::assertSame(['Eager'], $titles);
        self::assertSame('Eager', TestArticle::query()->value('title'));
    }

    public function testWithAuthorEagerLoadsManyArticlesWithOneRelatedQuery(): void
    {
        $author = TestAuthor::create(['name' => 'Dana']);
        TestArticle::create(['test_author_id' => (int) $author->id, 'title' => 'A']);
        TestArticle::create(['test_author_id' => (int) $author->id, 'title' => 'B']);
        TestArticle::create(['test_author_id' => (int) $author->id, 'title' => 'C']);

        $container = AppContext::container();
        $real = $container->make(Connection::class);
        $counter = new SqlCountingConnection($real);
        $container->instance(Connection::class, $counter);

        try {
            $authors = TestAuthor::query()->where('id', (int) $author->id)->with(['articles'])->get();
            self::assertCount(1, $authors);
            self::assertCount(3, $authors[0]->articles ?? []);
            self::assertSame(['A', 'B', 'C'], array_map(
                static fn (TestArticle $a): string => (string) $a->title,
                $authors[0]->articles,
            ));
            self::assertSame(2, $counter->selectCount, 'parent row + batched hasMany');
        } finally {
            $container->instance(Connection::class, $real);
        }
    }

    public function testInvalidEagerSpecThrows(): void
    {
        BadEagerSpecArticle::create(['title' => 'x']);
        $this->expectException(InvalidArgumentException::class);
        BadEagerSpecArticle::query()->with(['nope'])->get();
    }

    public function testNestedWithLoadsDeepBelongsTo(): void
    {
        $country = TestCountry::create(['code' => 'US']);
        $author = TestAuthor::create(['name' => 'Eve', 'test_country_id' => (int) $country->id]);
        TestArticle::create(['test_author_id' => (int) $author->id, 'title' => 'Nested']);

        $articles = TestArticle::query()->orderBy('id')->with(['author.country'])->get();
        self::assertCount(1, $articles);
        self::assertInstanceOf(TestAuthor::class, $articles[0]->author ?? null);
        self::assertInstanceOf(TestCountry::class, $articles[0]->author->country ?? null);
        self::assertSame('US', (string) ($articles[0]->author->country->code ?? ''));

        $container = AppContext::container();
        $real = $container->make(Connection::class);
        $counter = new SqlCountingConnection($real);
        $container->instance(Connection::class, $counter);
        try {
            TestArticle::query()->orderBy('id')->with(['author.country'])->get();
            self::assertSame(3, $counter->selectCount, 'articles + authors + countries');
        } finally {
            $container->instance(Connection::class, $real);
        }
    }

    public function testLoadEagerOnSingleModel(): void
    {
        $author = TestAuthor::create(['name' => 'Frank']);
        $article = TestArticle::create(['test_author_id' => (int) $author->id, 'title' => 'Solo']);
        $fresh = TestArticle::find((int) $article->id);
        self::assertNotNull($fresh);
        self::assertFalse(isset($fresh->author));
        $fresh->load('author');
        self::assertInstanceOf(TestAuthor::class, $fresh->author);
        self::assertSame('Frank', (string) ($fresh->author->name ?? ''));
    }

    public function testLoadWithNestedPathOnSingleModel(): void
    {
        $country = TestCountry::create(['code' => 'CA']);
        $author = TestAuthor::create(['name' => 'Grace', 'test_country_id' => (int) $country->id]);
        $article = TestArticle::create(['test_author_id' => (int) $author->id, 'title' => 'Deep']);
        $fresh = TestArticle::find((int) $article->id);
        self::assertNotNull($fresh);
        $fresh->load(['author.country']);
        self::assertInstanceOf(TestCountry::class, $fresh->author->country ?? null);
        self::assertSame('CA', (string) ($fresh->author->country->code ?? ''));
    }

    public function testHasOneLazyAndEager(): void
    {
        $author = TestAuthor::create(['name' => 'Ivy']);
        $profile = TestAuthorProfile::create(['test_author_id' => (int) $author->id, 'bio' => 'Hello']);

        $p = $author->profile();
        self::assertInstanceOf(TestAuthorProfile::class, $p);
        self::assertSame('Hello', (string) ($p->bio ?? ''));

        $fresh = TestAuthor::find((int) $author->id);
        self::assertNotNull($fresh);
        $authors = TestAuthor::query()->where('id', (int) $author->id)->with(['profile'])->get();
        self::assertCount(1, $authors);
        self::assertInstanceOf(TestAuthorProfile::class, $authors[0]->profile ?? null);
        self::assertSame((int) $profile->id, (int) ($authors[0]->profile->id ?? 0));
    }

    public function testHasOneEagerUsesBatchedQuery(): void
    {
        $a1 = TestAuthor::create(['name' => 'J1']);
        $a2 = TestAuthor::create(['name' => 'J2']);
        TestAuthorProfile::create(['test_author_id' => (int) $a1->id, 'bio' => 'p1']);
        TestAuthorProfile::create(['test_author_id' => (int) $a2->id, 'bio' => 'p2']);

        $container = AppContext::container();
        $real = $container->make(Connection::class);
        $counter = new SqlCountingConnection($real);
        $container->instance(Connection::class, $counter);
        try {
            $authors = TestAuthor::query()->whereIn('id', [(int) $a1->id, (int) $a2->id])->orderBy('id')->with(['profile'])->get();
            self::assertCount(2, $authors);
            self::assertSame('p1', (string) ($authors[0]->profile->bio ?? ''));
            self::assertSame('p2', (string) ($authors[1]->profile->bio ?? ''));
            self::assertSame(2, $counter->selectCount, 'authors + profiles');
        } finally {
            $container->instance(Connection::class, $real);
        }
    }

    public function testEagerLoadOntoMaterializedModels(): void
    {
        $author = TestAuthor::create(['name' => 'Hank']);
        $a = TestArticle::create(['test_author_id' => (int) $author->id, 'title' => 'X']);
        $b = TestArticle::create(['test_author_id' => (int) $author->id, 'title' => 'Y']);
        $one = TestArticle::find((int) $a->id);
        $two = TestArticle::find((int) $b->id);
        self::assertNotNull($one);
        self::assertNotNull($two);
        TestArticle::query()->with(['author'])->eagerLoadOnto([$one, $two]);
        self::assertSame('Hank', (string) ($one->author->name ?? ''));
        self::assertSame('Hank', (string) ($two->author->name ?? ''));
    }

    private function clearAppContext(): void
    {
        $ref = new \ReflectionClass(AppContext::class);
        $prop = $ref->getProperty('container');
        $prop->setAccessible(true);
        $prop->setValue(null, null);
    }
}

final class TestAuthor extends Model
{
    /** @var list<string> */
    protected static array $fillable = ['name', 'test_country_id'];
    protected static bool $timestamps = false;

    protected static function eagerRelations(): array
    {
        return [
            'articles' => Relation::hasMany(TestArticle::class, 'test_author_id'),
            'country' => Relation::belongsTo(TestCountry::class, 'test_country_id'),
            'profile' => Relation::hasOne(TestAuthorProfile::class, 'test_author_id'),
        ];
    }

    public function country(): ?TestCountry
    {
        /** @var TestCountry|null $c */
        $c = $this->belongsTo(TestCountry::class, 'test_country_id');

        return $c;
    }

    /**
     * @return list<TestArticle>
     */
    public function articles(): array
    {
        /** @var list<TestArticle> $articles */
        $articles = $this->hasMany(TestArticle::class, 'test_author_id');

        return $articles;
    }

    public function profile(): ?TestAuthorProfile
    {
        /** @var TestAuthorProfile|null $p */
        $p = $this->hasOne(TestAuthorProfile::class, 'test_author_id');

        return $p;
    }
}

final class TestAuthorProfile extends Model
{
    protected static ?string $table = 'test_author_profiles';

    /** @var list<string> */
    protected static array $fillable = ['test_author_id', 'bio'];
    protected static bool $timestamps = false;
}

final class TestArticle extends Model
{
    /** @var list<string> */
    protected static array $fillable = ['test_author_id', 'title'];
    protected static bool $timestamps = false;

    protected static function eagerRelations(): array
    {
        return [
            'author' => Relation::belongsTo(TestAuthor::class, 'test_author_id'),
            'tags' => Relation::belongsToMany(
                TestTag::class,
                'test_article_tags',
                'test_article_id',
                'test_tag_id',
            ),
        ];
    }

    public function author(): ?TestAuthor
    {
        /** @var TestAuthor|null $author */
        $author = $this->belongsTo(TestAuthor::class, 'test_author_id');

        return $author;
    }

    /**
     * @return list<TestTag>
     */
    public function tags(): array
    {
        /** @var list<TestTag> $tags */
        $tags = $this->belongsToMany(
            TestTag::class,
            'test_article_tags',
            'test_article_id',
            'test_tag_id',
        );

        return $tags;
    }
}

final class TestTag extends Model
{
    /** @var list<string> */
    protected static array $fillable = ['label'];
    protected static bool $timestamps = false;
}

final class TestCountry extends Model
{
    protected static ?string $table = 'test_countries';

    /** @var list<string> */
    protected static array $fillable = ['code'];
    protected static bool $timestamps = false;
}

/** @internal */
final class BadEagerSpecArticle extends Model
{
    protected static ?string $table = 'bad_eager_articles';

    /** @var list<string> */
    protected static array $fillable = ['title'];
    protected static bool $timestamps = false;

    protected static function eagerRelations(): array
    {
        return [
            'nope' => ['unknown_type', self::class],
        ];
    }
}

/**
 * Wraps a connection and counts SELECT statements (for eager-load batching tests).
 *
 * @internal
 */
final class SqlCountingConnection extends Connection
{
    public int $selectCount = 0;

    public function __construct(private Connection $inner)
    {
        parent::__construct($inner->pdo());
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function select(string $sql, array $bindings = []): array
    {
        ++$this->selectCount;

        return $this->inner->select($sql, $bindings);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function selectOne(string $sql, array $bindings = []): ?array
    {
        ++$this->selectCount;

        return $this->inner->selectOne($sql, $bindings);
    }

    public function execute(string $sql, array $bindings = []): int
    {
        return $this->inner->execute($sql, $bindings);
    }
}
