# Roadmap

Planned and not-yet-built capabilities relative to what Vortex already ships (HTTP, routing, DB + migrations + models/query builder, validation, sessions/CSRF, mail, file cache, storage, sync events, console, Twig, pagination, rate limiting).

## In progress / shipped slices

- **Authentication & authorization** — Session `Auth`, `Gate` (abilities + model policies), `RememberCookie` + `RememberFromCookie` middleware, `PasswordResetBroker` (SQL tokens), `Authenticate` / `AuthorizeAbility` middleware, Twig `auth_*` and `gate_allows`.

## Next chunks (pick in order or parallel)

Concrete follow-ups; each is a shippable vertical slice:

1. **Database queue + worker** — `jobs` table (migration contract), `Queue::push` / job interface, `php vortex queue:work` (or equivalent) polling the table, retries and `failed_jobs` optional in a second pass.

## Core platform

- **Authentication & authorization** — Shipped for the current scope (session login, remember-me cookie, gates/policies, reset token broker; apps wire mail and routes).
- **Queues & workers** — Job objects, queue contract + drivers (e.g. database table, Redis), and a worker entrypoint for async/background work.
- **Scheduling** — Cron-friendly scheduler (register recurring closures/commands; single CLI that runs due tasks).
- **Real-time** — Optional WebSockets/SSE or a thin broadcasting abstraction (channels, publish) if demand appears.

## Data & persistence

- **Cache drivers** — In-process Redis/Memcached (or PSR-16) implementations behind existing `Cache` / `Contracts\Cache`.
- **ORM depth** — Model observers/events, attribute casting, soft deletes, global scopes; richer relation API (lazy load, constrained eager loads) beyond current helpers + `QueryBuilder::with()`.
- **Schema builder** — Broader column/index/foreign-key coverage and dialect-specific pieces where needed.

## HTTP & API

- **API conveniences** — Optional JSON resource/transform layer, versioning helpers, consistent error envelope for APIs (beyond `Response::json()` + `Request::wantsJson()`).
- **Routing DX** — Route model binding, resource route groups, stronger controller + middleware conventions if we keep growing past closure/`[Class, 'method']` style.

## Developer experience

- **Container** — Method injection, tagged services, or contextual binding only if complexity stays justified for Vortex’s size.
- **Tooling** — Optional codegen (`make:command`, `make:migration`) and/or a small REPL; debug helpers (without pulling a full debugbar dependency by default).
- **Framework test kit** — Reusable HTTP/kernel test helpers for consuming applications (patterns exist in framework tests; not packaged as a first-class API yet).

## Packaging

- **Lockfile policy** — Library consumers lock in their apps; optional documented template or `composer.lock` for the **skeleton** app only (framework package may stay unlocked).

Items are not ordered by release; pick by impact and maintenance cost.
