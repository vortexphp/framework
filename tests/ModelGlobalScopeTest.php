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
use Vortex\Database\QueryBuilder;

final class ModelGlobalScopeTest extends TestCase
{
    private string $configDir = '';

    protected function setUp(): void
    {
        parent::setUp();
        Model::forgetRegisteredObservers();
        Model::forgetAllGlobalScopesForTesting();

        $this->configDir = sys_get_temp_dir() . '/vortex-gscope-' . bin2hex(random_bytes(4));
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
        );
        Repository::setInstance(new Repository($this->configDir));

        $container = new Container();
        $container->instance(Container::class, $container);
        $container->singleton(DatabaseManager::class, static fn (): DatabaseManager => DatabaseManager::fromRepository());
        $container->singleton(Connection::class, static fn (Container $c): Connection => $c->make(DatabaseManager::class)->connection());
        AppContext::set($container);

        ScopedRow::connection()->execute(
            'CREATE TABLE scoped_rows (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, label TEXT NOT NULL)',
        );

        ScopedRow::addGlobalScope('tenant', static function (QueryBuilder $q): void {
            $q->where('tenant_id', 1);
        });
        ScopedRow::addGlobalScope('published', static function (QueryBuilder $q): void {
            $q->where('label', '!=', 'draft');
        });
    }

    protected function tearDown(): void
    {
        ScopedRow::forgetGlobalScopes();
        Model::forgetAllGlobalScopesForTesting();
        Model::forgetRegisteredObservers();
        $ref = new \ReflectionClass(AppContext::class);
        $prop = $ref->getProperty('container');
        $prop->setAccessible(true);
        $prop->setValue(null, null);
        Repository::forgetInstance();
        if ($this->configDir !== '' && is_file($this->configDir . '/database.php')) {
            unlink($this->configDir . '/database.php');
            rmdir($this->configDir);
        }
        parent::tearDown();
    }

    public function testGlobalScopesConstrainQuery(): void
    {
        ScopedRow::create(['tenant_id' => 1, 'label' => 'a']);
        ScopedRow::create(['tenant_id' => 2, 'label' => 'b']);
        ScopedRow::create(['tenant_id' => 1, 'label' => 'draft']);

        self::assertSame(1, ScopedRow::query()->count());
        $labels = ScopedRow::query()->orderBy('label')->pluck('label');
        self::assertSame(['a'], $labels);
    }

    public function testWithoutGlobalScopeRemovesNamedConstraints(): void
    {
        ScopedRow::create(['tenant_id' => 1, 'label' => 'a']);
        ScopedRow::create(['tenant_id' => 2, 'label' => 'b']);

        self::assertSame(2, ScopedRow::query()->withoutGlobalScope('tenant')->count());
    }

    public function testWithoutGlobalScopesRemovesAllScopeConstraints(): void
    {
        ScopedRow::create(['tenant_id' => 1, 'label' => 'a']);
        ScopedRow::create(['tenant_id' => 2, 'label' => 'draft']);

        self::assertSame(2, ScopedRow::query()->withoutGlobalScopes()->count());
    }

    public function testManualWhereNotRemovedByWithoutGlobalScopes(): void
    {
        ScopedRow::create(['tenant_id' => 1, 'label' => 'a']);
        ScopedRow::create(['tenant_id' => 1, 'label' => 'b']);

        self::assertSame(
            1,
            ScopedRow::query()->withoutGlobalScopes()->where('label', 'a')->count(),
        );
    }

    public function testWhereGroupCombinesWithGlobalScopes(): void
    {
        ScopedRow::create(['tenant_id' => 1, 'label' => 'x']);
        ScopedRow::create(['tenant_id' => 1, 'label' => 'y']);

        $rows = ScopedRow::query()
            ->whereGroup(static function (QueryBuilder $q): void {
                $q->where('label', 'x')->orWhere('label', 'z');
            })
            ->get();

        self::assertCount(1, $rows);
        self::assertSame('x', (string) $rows[0]->label);
    }

    public function testFindAppliesGlobalScopes(): void
    {
        $other = ScopedRow::create(['tenant_id' => 2, 'label' => 'nope']);

        self::assertNull(ScopedRow::find((int) $other->id));
    }

    public function testAllAppliesGlobalScopes(): void
    {
        ScopedRow::create(['tenant_id' => 1, 'label' => 'in']);
        ScopedRow::create(['tenant_id' => 2, 'label' => 'out']);

        self::assertCount(1, ScopedRow::all());
    }
}

final class ScopedRow extends Model
{
    protected static ?string $table = 'scoped_rows';

    /** @var list<string> */
    protected static array $fillable = ['tenant_id', 'label'];

    protected static bool $timestamps = false;
}
