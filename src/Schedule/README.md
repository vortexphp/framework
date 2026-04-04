# Schedule module

Cron-style recurring tasks triggered by the **`schedule:run`** CLI (intended to run **once per minute** from system cron).

## Config

Create `config/schedule.php`:

```php
<?php

declare(strict_types=1);

return [
    'mutex_store' => 'redis',
    'tasks' => [
        ['cron' => '0 * * * *', 'class' => \App\Tasks\HourlyHeartbeat::class],
        [
            'cron' => '*/5 * * * *',
            'class' => \App\Tasks\FiveMinutePoll::class,
            'without_overlapping' => true,
            'mutex_ttl' => 3600,
        ],
    ],
];
```

Each handler class is resolved from the container and must define **`__invoke(): void`** or **`handle(): void`**.

## Programmatic registration

Inside `Application::boot($base, configure)`:

```php
use Vortex\Schedule\Schedule;

Schedule::register('* * * * *', \App\Tasks\EveryMinute::class, [
    'without_overlapping' => true,
    'mutex_ttl' => 900,
]);
```

File-based tasks load first; `register()` appends more definitions during the same boot.

## Cron syntax

Five fields: **minute hour day-of-month month day-of-week** (Sunday = `0` as in PHP’s `w` format).

Per field: **`*`**, a **single integer**, a **step** like **`*/15`**, **comma lists** (e.g. `1,2,3`), or **hyphen ranges** (e.g. `9-17`). Steps inside ranges (e.g. `1-10/2`) are not supported.

## Overlap guard

Set **`without_overlapping`** on a task (config row or `Schedule::register` options). The runner acquires a mutex with **`Cache::add()`** on the store named by **`mutex_store`** in this config (falls back to the default cache store when omitted). Use a **Redis** cache store for cross-process locking. **`mutex_ttl`** / **`mutex_ttl_seconds`** clamp between 30 and 86400 seconds (default 3600).

## Time zone

`Schedule::runDue()` uses **`app.timezone`** from config when building “now” (default `UTC`).

## System cron

```cron
* * * * * cd /path/to/app && php vortex schedule:run >> /path/to/storage/logs/schedule.log 2>&1
```

Adjust the path and PHP entry to match your deployment.
