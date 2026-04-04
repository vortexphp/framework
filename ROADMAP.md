# Roadmap

Planned and not-yet-built capabilities relative to what Vortex already ships (HTTP, routing, DB + migrations + models/query builder, validation, sessions/CSRF, mail, file cache, storage, sync events, console, Twig, pagination, rate limiting).

## In progress / shipped slices

- **Authentication & authorization** — Session `Auth`, `Gate` (abilities + model policies), `RememberCookie` + `RememberFromCookie` middleware, `PasswordResetBroker` (SQL tokens), `Authenticate` / `AuthorizeAbility` middleware, Twig `auth_*` and `gate_allows`.
- **Database queue + worker** — `Vortex\Queue\Contracts\Job`, `DatabaseQueue`, `Queue::push`, `queue:work`, `queue:failed`, `queue:retry` (incl. `all`), `FailedJobStore` + optional `queue.failed_jobs_table`.
- **Scheduling** — `config/schedule.php` tasks, `Schedule::register()`, five-field cron matching (`CronExpression`), CLI **`schedule:run`**.
- **Cache: Redis** — `RedisCache` + `cache.stores.*` driver **`redis`** (phpredis / **ext-redis**).
- **Cache: Memcached** — `MemcachedCache` + driver **`memcached`** (**ext-memcached**).
- **Queue: Redis** — `RedisQueue`, `QueueDriver` contract, `queue.driver` / `queue.redis`, shared `PhpRedisConnect` with cache.
- **ORM: observers** — `Model::observe()` with `creating` / `updated` / … hooks on `create()`, `save()`, `delete()`.
- **ORM: casts** — `$casts` on `Model` for int/float/bool/string/json/datetime.
- **ORM: soft deletes** — `$softDeletes` / `$deletedAtColumn`, query scopes, `restore` / `forceDelete`.
- **ORM: global scopes** — `addGlobalScope`, `withoutGlobalScope(s)`; `find` / `all` via `query()`.
- **ORM: eager `with()`** — `Model::eagerRelations()` for batched `belongsTo` / `hasMany` / `belongsToMany`; nested dot paths (e.g. `author.country`); **`Relation`** spec builders, **`Model::load()`**, **`QueryBuilder::eagerLoadOnto()`**.
- **Routing: model binding** — `Router::model` / `Router::bind` (+ `Route::model` / `Route::bind`); missing model or `null` resolver → 404.
- **Routing: resource groups** — `Router::resource` / `Route::resource` (REST index/store/show/update/destroy; optional create/edit).
- **HTTP: JSON API envelope** — `Response::apiOk` / `apiError`, `JsonResource`, `validationFailed(ValidationResult)`, `Request::validationResponse` / `bodyValidationResponse`, **`JsonShape`** / **`object`** / **`listOfObjects`** / **`listOfPrimitive`** + **`bodyShapeResponse`**, path/header API version helpers (`splitVersionedPath`, `resolvedApiVersion`, `withPath`), aligned negotiation errors + `ErrorRenderer` JSON.
- **Testing helpers** — `Vortex\Testing\KernelBrowser` for in-process `Kernel` dispatch + `Container::has()`.
- **Schema builder** — extra column types (`decimal`, `json`, `date`, `floatType`, `char`, sized integers), `Schema::hasTable`, FK `ON UPDATE`, MySQL `UNSIGNED` on integers.
- **HTTP controllers** — optional **`Controller`** base + invokable class routes + per-route **`middleware()`** chaining.

## Next chunks (pick in order or parallel)

Concrete follow-ups; each is a shippable vertical slice:

1. **Developer experience** — optional codegen (`make:command`, `make:migration`) or small REPL.

## Core platform

- **Authentication & authorization** — Shipped for the current scope (session login, remember-me cookie, gates/policies, reset token broker; apps wire mail and routes).
- **Queues & workers** — Shipped: SQL + Redis drivers, worker CLI, failed-job persistence + replay.
- **Scheduling** — Shipped: `schedule:run`, config + programmatic tasks, cron lists/ranges, overlap guard via **`Cache::add`** and **`schedule.mutex_store`** (use a Redis-backed store for distributed mutexes).
- **Real-time** — Optional WebSockets/SSE or a thin broadcasting abstraction (channels, publish) if demand appears.

## Data & persistence

- **Cache drivers** — **Redis** (phpredis) and **Memcached** (ext-memcached) behind `Cache`; file + null unchanged. PSR-16 optional later.
- **ORM depth** — Shipped: model observers, attribute casts, soft deletes, global scopes, batched **`with()`** including nested dot paths. Remaining: relation objects or richer conveniences as needed.
- **Schema builder** — Shipped: more column types (**`decimal`**, **`json`**, **`date`**, **`floatType`**, integer sizes, **`char`**), **`Schema::hasTable`**, FK **`ON UPDATE`**, MySQL **`UNSIGNED`** on integer columns.

## HTTP & API

- **API conveniences** — Shipped: **`JsonResource`**, **`Response::apiOk`/`apiError`/`validationFailed`**, **`Request` validation + version + `JsonShape` helpers**, negotiated errors with **`error`** codes. Optional: deeper transform pipelines, full JSON Schema.
- **Routing DX** — Shipped: route model binding, custom `bind`, **`Route::resource`**, invokable class routes, **`middleware()`** on the last route, optional **`Controller`** base helpers.

## Developer experience

- **Container** — Method injection, tagged services, or contextual binding only if complexity stays justified for Vortex’s size.
- **Tooling** — Optional codegen (`make:command`, `make:migration`) and/or a small REPL; debug helpers (without pulling a full debugbar dependency by default).
- **Framework test kit** — Shipped: **`KernelBrowser`** (`Vortex\Testing`) for consuming app PHPUnit tests.

## Packaging

- **Lockfile policy** — Library consumers lock in their apps; optional documented template or `composer.lock` for the **skeleton** app only (framework package may stay unlocked).

Items are not ordered by release; pick by impact and maintenance cost.
