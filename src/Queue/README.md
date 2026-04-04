# Queue module

Database-backed job queue: push serializable {@see \Vortex\Queue\Contracts\Job} objects, process them with `php vortex queue:work` (or embed {@see \Vortex\Queue\DatabaseQueue} in tests).

## Table

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

## Config (`config` repository)

| Key | Default | Purpose |
|-----|---------|---------|
| `queue.table` | `jobs` | SQL table name (alphanumeric / underscore). |
| `queue.default` | `default` | Queue name for {@see \Vortex\Queue\Queue::push()} when none is passed. |
| `queue.tries` | `3` | After this many failures the row is deleted and the error is logged. |
| `queue.stale_reserve_seconds` | `300` | If a worker dies while holding a job, reclaim the row after this many seconds. |
| `queue.idle_sleep_ms` | `1000` | When `queue:work` is polling and the queue is empty, sleep this long between attempts. |

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
```

The command boots the full application (same database config as HTTP).

## Direct API

Inject {@see \Vortex\Queue\DatabaseQueue} from the container, or `new DatabaseQueue($connection, 'custom_jobs')` in tests.
