<?php

declare(strict_types=1);

namespace Vortex\Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use Vortex\Database\Connection;
use Vortex\Database\Schema\SchemaMigrator;

final class SchemaMigratorTest extends TestCase
{
    private string $base = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->base = sys_get_temp_dir() . '/vortex-migrator-' . bin2hex(random_bytes(4));
        mkdir($this->base . '/db/migrations', 0700, true);
    }

    protected function tearDown(): void
    {
        if ($this->base !== '' && is_dir($this->base)) {
            foreach (glob($this->base . '/db/migrations/*.php') ?: [] as $f) {
                unlink($f);
            }
            @rmdir($this->base . '/db/migrations');
            @rmdir($this->base . '/db');
            @rmdir($this->base);
        }
        parent::tearDown();
    }

    public function testUpThenDownLastBatch(): void
    {
        file_put_contents($this->base . '/db/migrations/001_create_users.php', <<<'PHP'
<?php
use Vortex\Database\Connection;
use Vortex\Database\Schema\Migration;

return new class implements Migration {
    public function id(): string { return '001_create_users'; }
    public function up(Connection $db): void { $db->pdo()->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL)'); }
    public function down(Connection $db): void { $db->pdo()->exec('DROP TABLE users'); }
};
PHP);
        file_put_contents($this->base . '/db/migrations/002_create_posts.php', <<<'PHP'
<?php
use Vortex\Database\Connection;
use Vortex\Database\Schema\Migration;

return new class implements Migration {
    public function id(): string { return '002_create_posts'; }
    public function up(Connection $db): void { $db->pdo()->exec('CREATE TABLE posts (id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL)'); }
    public function down(Connection $db): void { $db->pdo()->exec('DROP TABLE posts'); }
};
PHP);

        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $db = new Connection($pdo);
        $migrator = new SchemaMigrator($this->base, $db);

        self::assertSame(2, $migrator->up());
        self::assertSame(0, $migrator->up());
        self::assertNotNull($db->selectOne("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'users'"));
        self::assertNotNull($db->selectOne("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'posts'"));

        self::assertSame(2, $migrator->down());
        self::assertNull($db->selectOne("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'users'"));
        self::assertNull($db->selectOne("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'posts'"));
        self::assertSame(0, $migrator->down());
    }

    public function testCustomMigrationsDirectoryFromPathsConfig(): void
    {
        $base = sys_get_temp_dir() . '/vortex-migrator-paths-' . bin2hex(random_bytes(4));
        mkdir($base . '/config', 0700, true);
        file_put_contents(
            $base . '/config/paths.php',
            "<?php\n\ndeclare(strict_types=1);\n\nreturn ['migrations' => 'alt/migrations'];\n",
        );
        mkdir($base . '/alt/migrations', 0700, true);
        try {
            file_put_contents($base . '/alt/migrations/001_x.php', <<<'PHP'
<?php
use Vortex\Database\Connection;
use Vortex\Database\Schema\Migration;

return new class implements Migration {
    public function id(): string { return '001_x'; }
    public function up(Connection $db): void { $db->pdo()->exec('CREATE TABLE x (id INTEGER PRIMARY KEY)'); }
    public function down(Connection $db): void { $db->pdo()->exec('DROP TABLE x'); }
};
PHP);

            $pdo = new PDO('sqlite::memory:', null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $db = new Connection($pdo);
            $migrator = new SchemaMigrator($base, $db);

            self::assertSame(1, $migrator->up());
            self::assertNotNull($db->selectOne("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'x'"));
        } finally {
            foreach (glob($base . '/alt/migrations/*.php') ?: [] as $f) {
                unlink($f);
            }
            @rmdir($base . '/alt/migrations');
            @rmdir($base . '/alt');
            unlink($base . '/config/paths.php');
            @rmdir($base . '/config');
            @rmdir($base);
        }
    }
}
