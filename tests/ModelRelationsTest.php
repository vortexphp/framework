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
        TestArticle::connection()->execute(
            'CREATE TABLE test_articles (id INTEGER PRIMARY KEY AUTOINCREMENT, test_author_id INTEGER NOT NULL, title TEXT NOT NULL)'
        );
        TestTag::connection()->execute('CREATE TABLE test_tags (id INTEGER PRIMARY KEY AUTOINCREMENT, label TEXT NOT NULL)');
        TestArticle::connection()->execute(
            'CREATE TABLE test_article_tags (id INTEGER PRIMARY KEY AUTOINCREMENT, test_article_id INTEGER NOT NULL, test_tag_id INTEGER NOT NULL)'
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
    protected static array $fillable = ['name'];
    protected static bool $timestamps = false;

    /**
     * @return list<TestArticle>
     */
    public function articles(): array
    {
        /** @var list<TestArticle> $articles */
        $articles = $this->hasMany(TestArticle::class, 'test_author_id');

        return $articles;
    }
}

final class TestArticle extends Model
{
    /** @var list<string> */
    protected static array $fillable = ['test_author_id', 'title'];
    protected static bool $timestamps = false;

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
