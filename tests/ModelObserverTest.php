<?php

declare(strict_types=1);

namespace Vortex\Tests;

use LogicException;
use PHPUnit\Framework\TestCase;
use Vortex\AppContext;
use Vortex\Config\Repository;
use Vortex\Container;
use Vortex\Database\Connection;
use Vortex\Database\DatabaseManager;
use Vortex\Database\Model;

final class ModelObserverTest extends TestCase
{
    private string $configDir = '';

    protected function setUp(): void
    {
        parent::setUp();
        Model::forgetRegisteredObservers();

        $this->configDir = sys_get_temp_dir() . '/vortex-model-obs-' . bin2hex(random_bytes(4));
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

        ObservedThing::connection()->execute(
            'CREATE TABLE observed_things (id INTEGER PRIMARY KEY AUTOINCREMENT, label TEXT NOT NULL, bumped INTEGER DEFAULT 0)',
        );
    }

    protected function tearDown(): void
    {
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

    public function testCreateFiresSavingCreatingCreatedSavedInOrder(): void
    {
        $cap = new ObserverCapture();
        ObservedThing::observe($cap);

        ObservedThing::create(['label' => 'a']);

        self::assertSame(
            ['saving', 'creating', 'created', 'saved'],
            $cap->events,
        );
    }

    public function testCreatingCanMutateBeforeInsert(): void
    {
        ObservedThing::observe(new class {
            public function creating(ObservedThing $m): void
            {
                $m->label = strtoupper((string) $m->label);
            }
        });

        $row = ObservedThing::create(['label' => 'hello']);

        self::assertSame('HELLO', (string) $row->label);
        $again = ObservedThing::find((int) $row->id);
        self::assertSame('HELLO', (string) ($again->label ?? ''));
    }

    public function testSaveUpdateFiresSavingUpdatingUpdatedSaved(): void
    {
        $row = ObservedThing::create(['label' => 'x']);
        Model::forgetRegisteredObservers();

        $cap = new ObserverCapture();
        ObservedThing::observe($cap);

        $row->label = 'y';
        $row->save();

        self::assertSame(['saving', 'updating', 'updated', 'saved'], $cap->events);
    }

    public function testDeleteFiresDeletingDeleted(): void
    {
        $row = ObservedThing::create(['label' => 'del']);

        $cap = new ObserverCapture();
        ObservedThing::observe($cap);

        $row->delete();

        self::assertSame(['deleting', 'deleted'], $cap->events);
    }

    public function testInsertWithNoPayloadThrows(): void
    {
        $m = new ObservedThing();
        $this->expectException(LogicException::class);
        $m->save();
    }
}

final class ObserverCapture
{
    /** @var list<string> */
    public array $events = [];

    public function saving(Model $m): void
    {
        $this->events[] = 'saving';
    }

    public function creating(Model $m): void
    {
        $this->events[] = 'creating';
    }

    public function updating(Model $m): void
    {
        $this->events[] = 'updating';
    }

    public function deleting(Model $m): void
    {
        $this->events[] = 'deleting';
    }

    public function saved(Model $m): void
    {
        $this->events[] = 'saved';
    }

    public function created(Model $m): void
    {
        $this->events[] = 'created';
    }

    public function updated(Model $m): void
    {
        $this->events[] = 'updated';
    }

    public function deleted(Model $m): void
    {
        $this->events[] = 'deleted';
    }
}

final class ObservedThing extends Model
{
    protected static ?string $table = 'observed_things';

    /** @var list<string> */
    protected static array $fillable = ['label', 'bumped'];

    protected static bool $timestamps = false;
}
