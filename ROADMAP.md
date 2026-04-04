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
- **ORM: eager `with()`** — `Model::eagerRelations()` for batched `belongsTo` / `hasMany` / `belongsToMany`.
- **Routing: model binding** — `Router::model` / `Router::bind` (+ `Route::model` / `Route::bind`); missing model or `null` resolver → 404.

## Next chunks (pick in order or parallel)

Concrete follow-ups; each is a shippable vertical slice:

1. **ORM / HTTP** — nested `with()`, relation API polish, or API helpers (resources, error envelope).

## Core platform

- **Authentication & authorization** — Shipped for the current scope (session login, remember-me cookie, gates/policies, reset token broker; apps wire mail and routes).
- **Queues & workers** — Shipped: SQL + Redis drivers, worker CLI, failed-job persistence + replay.
- **Scheduling** — Shipped: `schedule:run`, config + programmatic tasks, cron lists/ranges, overlap guard via **`Cache::add`** and **`schedule.mutex_store`** (use a Redis-backed store for distributed mutexes).
- **Real-time** — Optional WebSockets/SSE or a thin broadcasting abstraction (channels, publish) if demand appears.

## Data & persistence

- **Cache drivers** — **Redis** (phpredis) and **Memcached** (ext-memcached) behind `Cache`; file + null unchanged. PSR-16 optional later.
- **ORM depth** — Shipped: model observers, attribute casts, soft deletes, global scopes, batched **`with()`** via **`Model::eagerRelations()`** (`belongsTo` / `hasMany` / `belongsToMany`). Remaining: nested `with('a.b')`, relation objects, or richer conveniences as needed.
- **Schema builder** — Broader column/index/foreign-key coverage and dialect-specific pieces where needed.

## HTTP & API

- **API conveniences** — Optional JSON resource/transform layer, versioning helpers, consistent error envelope for APIs (beyond `Response::json()` + `Request::wantsJson()`).
- **Routing DX** — Shipped: route model binding and custom `bind`. Remaining: resource route groups, stronger controller + middleware conventions if we keep growing past closure/`[Class, 'method']` style.

## Developer experience

- **Container** — Method injection, tagged services, or contextual binding only if complexity stays justified for Vortex’s size.
- **Tooling** — Optional codegen (`make:command`, `make:migration`) and/or a small REPL; debug helpers (without pulling a full debugbar dependency by default).
- **Framework test kit** — Reusable HTTP/kernel test helpers for consuming applications (patterns exist in framework tests; not packaged as a first-class API yet).

## Packaging

- **Lockfile policy** — Library consumers lock in their apps; optional documented template or `composer.lock` for the **skeleton** app only (framework package may stay unlocked).

Items are not ordered by release; pick by impact and maintenance cost.
