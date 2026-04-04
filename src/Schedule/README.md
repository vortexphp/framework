# Schedule module

Cron-style recurring tasks triggered by the **`schedule:run`** CLI (intended to run **once per minute** from system cron).

## Config

Create `config/schedule.php`:

```php
<?php

declare(strict_types=1);

return [
    'tasks' => [
        ['cron' => '0 * * * *', 'class' => \App\Tasks\HourlyHeartbeat::class],
        ['cron' => '*/5 * * * *', 'class' => \App\Tasks\FiveMinutePoll::class],
    ],
];
```

Each handler class is resolved from the container and must define **`__invoke(): void`** or **`handle(): void`**.

## Programmatic registration

Inside `Application::boot($base, configure)`:

```php
use Vortex\Schedule\Schedule;

Schedule::register('* * * * *', \App\Tasks\EveryMinute::class);
```

File-based tasks load first; `register()` appends more definitions during the same boot.

## Cron syntax

Five fields: **minute hour day-of-month month day-of-week** (Sunday = `0` as in PHP’s `w` format).

Per field, only **`*`**, a **single integer**, or a **step** like **`*/15`** (every 15 units). No lists or ranges.

## Time zone

`Schedule::runDue()` uses **`app.timezone`** from config when building “now” (default `UTC`).

## System cron

```cron
* * * * * cd /path/to/app && php vortex schedule:run >> /path/to/storage/logs/schedule.log 2>&1
```

Adjust the path and PHP entry to match your deployment.
