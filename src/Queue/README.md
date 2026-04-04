# Queue module

Push serializable {@see \Vortex\Queue\Contracts\Job} objects and run them with `php vortex queue:work`. The active backend is {@see \Vortex\Queue\Contracts\QueueDriver}: **database** (default) or **Redis** (`queue.driver`).

## Drivers

| `queue.driver` | Class | Notes |
|----------------|-------|--------|
| `database` (default) | {@see \Vortex\Queue\DatabaseQueue} | SQL `jobs` table + row reservation / stale reclaim. |
| `redis` | {@see \Vortex\Queue\RedisQueue} | Lists + delayed sorted sets; requires **ext-redis**. `queue.stale_reserve_seconds` is ignored (POP is the claim). |

Redis config (`config/queue.php` or equivalent):

```php
'driver' => 'redis',
'redis' => [
    'host' => '127.0.0.1',
    'port' => 6379,
    'password' => null,
    'database' => 2,  // use a dedicated DB index for queues
    'prefix' => 'myapp:q:',
    'timeout' => 0.0,
    'persistent' => false,
],
```

Connection options mirror the cache Redis store (see `src/Cache/README.md`). **PhpRedisConnect** is shared for both.

## Table (database driver)

Create a `jobs` table (name overridable via config `queue.table`):

```sql
CREATE TABLE jobs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    queue VARCHAR(255) NOT NULL,
    payload TEXT NOT NULL,
    attempts INTEGER NOT NULL DEFAULT 0,
    reserved_at INTEGER NULL,
    available_at INTEGER NOT NULL,
    created_at INTEGER NOT NULL
);
```

For MySQL, use `INT` / `BIGINT` and `AUTO_INCREMENT` instead of `INTEGER PRIMARY KEY AUTOINCREMENT`.

## Failed jobs table

When a job exceeds `queue.tries`, the worker discards it from the driver (SQL row deleted or Redis POP already done) and inserts a row into the failed store (if enabled). Suggested schema:

```sql
CREATE TABLE failed_jobs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    queue VARCHAR(255) NOT NULL,
    payload TEXT NOT NULL,
    exception TEXT NOT NULL,
    failed_at INTEGER NOT NULL
);
```

Set `queue.failed_jobs_table` to `''` (empty string) in `config/queue.php` to skip recording (failures are only logged).

## Config (`config` repository)

| Key | Default | Purpose |
|-----|---------|---------|
| `queue.table` | `jobs` | SQL table name (alphanumeric / underscore). |
| `queue.default` | `default` | Queue name for {@see \Vortex\Queue\Queue::push()} when none is passed. |
| `queue.tries` | `3` | After this many failures the row is deleted and the error is logged. |
| `queue.stale_reserve_seconds` | `300` | If a worker dies while holding a job, reclaim the row after this many seconds. |
| `queue.idle_sleep_ms` | `1000` | When `queue:work` is polling and the queue is empty, sleep this long between attempts. |
| `queue.failed_jobs_table` | `failed_jobs` | Table for permanent failures; use `false` or `''` to disable {@see \Vortex\Queue\FailedJobStore::record()}. |
| `queue.driver` | `database` | `database` or `redis`. |
| `queue.redis` | `[]` | Connection array when `queue.driver` is `redis` (see above). |

## Pushing jobs

After `Application::boot()`:

```php
use Vortex\Queue\Contracts\Job;
use Vortex\Queue\Queue;

final class SendReport implements Job
{
    public function __construct(
        private readonly int $userId,
    ) {
    }

    public function handle(): void
    {
        // ...
    }
}

Queue::push(new SendReport(42));
Queue::push(new SendReport(99), queue: 'reports', delaySeconds: 60);
```

Jobs are stored with PHP `serialize()`. Workers use `unserialize(..., ['allowed_classes' => true])`; only enqueue classes you trust.

## Worker CLI

```bash
php vortex queue:work              # poll forever
php vortex queue:work once         # process at most one job, then exit
php vortex queue:work emails once  # named queue + single pass
php vortex queue:failed            # list recent failures (optional limit token)
php vortex queue:retry 3          # re-enqueue failed job #3
php vortex queue:retry all        # re-enqueue every stored failure
```

The command boots the full application (same database config as HTTP).

## Direct API

Resolve {@see \Vortex\Queue\Contracts\QueueDriver} from the container, or use {@see \Vortex\Queue\DatabaseQueue} / {@see \Vortex\Queue\RedisQueue} directly in tests.
