<?php

declare(strict_types=1);

namespace Vortex\Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use Vortex\Database\Connection;
use Vortex\Database\Schema\Schema;

final class SchemaBuilderTest extends TestCase
{
    private Connection $db;

    protected function setUp(): void
    {
        parent::setUp();
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $this->db = new Connection($pdo);
        Schema::usingConnection($this->db);
    }

    protected function tearDown(): void
    {
        Schema::clearConnectionResolver();
        parent::tearDown();
    }

    public function testCreateWithColumnsIndexesAndForeignKeys(): void
    {
        Schema::create('users', static function ($table): void {
            $table->id();
            $table->string('email')->unique();
        });

        Schema::create('posts', static function ($table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()->index('idx_posts_user_id');
            $table->string('slug')->unique();
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });

        $postColumns = $this->db->select('PRAGMA table_info(posts)');
        $names = array_values(array_map(static fn (array $row): string => (string) $row['name'], $postColumns));
        self::assertSame(['id', 'user_id', 'slug', 'is_published', 'created_at', 'updated_at'], $names);

        $indexes = $this->db->select('PRAGMA index_list(posts)');
        $indexNames = array_values(array_map(static fn (array $row): string => (string) $row['name'], $indexes));
        self::assertContains('idx_posts_user_id', $indexNames);
        self::assertContains('posts_slug_unique', $indexNames);

        $foreigns = $this->db->select('PRAGMA foreign_key_list(posts)');
        self::assertSame('users', (string) ($foreigns[0]['table'] ?? ''));
        self::assertSame('CASCADE', (string) ($foreigns[0]['on_delete'] ?? ''));
    }

    public function testTableAddColumnAndDropIfExists(): void
    {
        Schema::create('users', static function ($table): void {
            $table->id();
            $table->string('name');
        });

        Schema::table('users', static function ($table): void {
            $table->string('email')->nullable()->index('idx_users_email');
        });

        $columns = $this->db->select('PRAGMA table_info(users)');
        $names = array_values(array_map(static fn (array $row): string => (string) $row['name'], $columns));
        self::assertSame(['id', 'name', 'email'], $names);

        Schema::dropIfExists('users');
        self::assertNull($this->db->selectOne("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'users'"));
    }

    public function testHasTable(): void
    {
        self::assertFalse(Schema::hasTable('missing'));
        Schema::create('widgets', static function ($table): void {
            $table->id();
        });
        self::assertTrue(Schema::hasTable('widgets'));
        Schema::dropIfExists('widgets');
        self::assertFalse(Schema::hasTable('widgets'));
    }

    public function testExtendedColumnTypes(): void
    {
        Schema::create('demo_types', static function ($table): void {
            $table->id();
            $table->char('code', 3);
            $table->bigInteger('big_n');
            $table->smallInteger('small_n');
            $table->decimal('amount', 10, 4);
            $table->floatType('score');
            $table->date('day');
            $table->dateTime('started_at');
            $table->json('meta');
        });

        $info = $this->db->select('PRAGMA table_info(demo_types)');
        $types = [];
        foreach ($info as $row) {
            $types[(string) $row['name']] = (string) $row['type'];
        }

        self::assertStringContainsString('CHAR(3)', $types['code']);
        self::assertSame('INTEGER', $types['big_n']);
        self::assertSame('INTEGER', $types['small_n']);
        self::assertStringStartsWith('DECIMAL', $types['amount']);
        self::assertSame('REAL', $types['score']);
        self::assertSame('DATE', $types['day']);
        self::assertSame('DATETIME', $types['started_at']);
        self::assertSame('TEXT', $types['meta']);
    }

    public function testForeignKeyOnUpdate(): void
    {
        Schema::create('parents', static function ($table): void {
            $table->id();
        });
        Schema::create('children', static function ($table): void {
            $table->id();
            $table->foreignId('parent_id')->constrained('parents')->cascadeOnUpdate();
        });

        $fk = $this->db->select('PRAGMA foreign_key_list(children)');
        self::assertSame('CASCADE', (string) ($fk[0]['on_update'] ?? ''));
    }
}
