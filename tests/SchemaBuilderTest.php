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
}
