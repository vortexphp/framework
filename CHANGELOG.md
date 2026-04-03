# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.3.0] - 2026-04-03

### Added

- **Class-based database migrations** — `database/migrations/*.php` classes with `id()`, `up()`, and `down()`; **`SchemaMigrator`** and **`Vortex\Database\Schema`** (`Schema`, `Blueprint`, `ColumnDefinition`, `Migration`); **`php vortex migrate`** and **`migrate:down`** (rollback last batch); state in **`vortex_migrations`**.
- **`Database\Schema\Schema`** fluent builder with Laravel-like columns (`id`, `string`, `text`, `integer`, `boolean`, `timestamp`, `timestamps`, `foreignId`, `index`, `unique`).
- **`mockery/mockery`** as a dev dependency; **`MockeryIntegrationTest`** exercises container wiring with mocks.
- **`Application::boot()`** loads **`.env`** via **`Env::load`**, registers **`Csrf`**, **`LocalPublicStorage`**, **`Translator`**, and **`ErrorRenderer`**, shares **`appName`** into Twig, and accepts an optional **`?callable $configure(Container, string $basePath)`** after defaults (before route discovery).

### Changed

- **Breaking:** Database migrations are class-based (see Added). Older ad-hoc migration formats are not supported by **`migrate`**.

## [0.2.0] - 2026-04-03

### Added

- **`php vortex doctor`** — when **`config/files.php`** exists, checks each upload profile’s **`directory`**: exists under **`public/`**, writable, create/delete probe. **`FilesConfigUploadRoots`** parses the config shape.
- **`Log::setBasePath()`** at bootstrap; **`Log::info`**, **`warning`**, **`error`**, **`debug`**, **`notice`**, **`critical`**, **`alert`**, **`emergency`**, **`log($level, …)`** with optional JSON **`$context`**; same **`storage/logs/app.log`** as exceptions.
- **`Cookie`** value object (**`Set-Cookie`** via **`Response::cookie()`** or **`Cookie::queue()`** + **`Cookie::flushQueued()`** in **`Kernel`** / **`Application::run()`**), **`Request::cookie()`** / **`cookies()`**, **`Cookie::parseRequestHeader()`** (quoted values, **`SameSite`** helper shared with **`Session`**).
- **`Files\Storage`** façade: **`disk($name)`** returns **`Filesystem`** drivers from **`config/storage.php`** (**`local`**, **`local_public`**, **`null`**); default disk for **`put`/`get`/…**; **`storeUpload`** / **`publicRoot`** use **`upload_disk`** / **`public_disk`**. **`Storage::setBasePath($basePath)`** at bootstrap (**`Application::boot`** and app bootstrap).
- **`Support\Benchmark`** static stopwatch helper with named timers: **`start`**, **`has`**, **`elapsedNs`**, **`elapsedMs`**, **`elapsedSeconds`**, **`measure`**, **`forget`**.

### Changed

- **Breaking:** Database is multi-connection: **`config/database.php`** uses **`default`** and **`connections.{name}.driver`** (sqlite, mysql, pgsql). **`Vortex\Database\DatabaseManager`** is registered in the container; **`Connection`** is constructed with a **`PDO`** from the manager; **`DB::connection(?string)`** selects a connection. Env: **`DB_CONNECTION`** (default connection name, default `default`).
- **Breaking:** Cache is multi-store: **`config/cache.php`** uses **`default`** and **`stores.{name}.driver`** (like storage disks). **`Vortex\Cache\CacheManager`** is registered in the container; **`Cache::store(?string)`** selects a store; **`CacheFactory::make()`** returns the default store via the manager. Env: **`CACHE_STORE`** (default store name), **`CACHE_DRIVER`** only used when **`CACHE_STORE`** is unset.
- **Breaking:** Session is multi-store: **`config/session.php`** uses **`default`** and **`stores.{name}.driver`** (`native`, `null`). **`Vortex\Http\SessionManager`** is registered in the container; **`Session`** facade uses the default store; **`Session::store(?string)`** selects a store; **`Csrf`** now reads/writes through the `Session` facade. Env: **`SESSION_STORE`** controls default store name.
- **Breaking:** `QueryBuilder::paginate()` returns `Vortex\Pagination\Paginator` instead of an array. Use `$paginator->items` and the same public count fields (`total`, `page`, `per_page`, `last_page`). For page links, call `withBasePath()` (e.g. with `route('name')`) then `urlForPage($n)`; helpers `hasPages()`, `onFirstPage()`, and `onLastPage()` are available for templates.
- **Breaking:** **`Log::exception(Throwable $e)`** only — project root comes from **`Log::setBasePath()`** (**`Application::boot()`** and app bootstrap call it). **`ErrorRenderer`** has no constructor parameters.

## [0.1.0] - 2026-04-03

### Breaking

- **HTTP route files** (`app/Routes/*.php` except `*Console.php`) are **required** at discovery time and must register routes at the top level with `Route::get` / `post` / `add` — no `return static function (): void { … }` wrapper. Console route files still `return callable(ConsoleApplication): void`.

### Added

- **Cache-backed rate limiting and stricter doctor** — `RateLimiter`, `Middleware\Throttle` (fixed window); `php vortex doctor` requires `ext-mbstring`, and production checks require non-empty `APP_KEY`.
- **Named routes and URL generation** — `Router::name`, `Router::path`, `Route::name()`, global `route()`, Twig `route()`, `Router::interpolatePattern()`.
- **In-process HTTP handling and route loading** — `Kernel::handle(Request)`, `Request::make()` / `normalizePath()`, `Response::headers()` for tests; HTTP route files are loaded via `require` (see Breaking). Fixture app and `KernelHandleTest` in the framework test suite.

### Changed

- `Kernel::send()` applies `TrustProxies`, builds the request, delegates to `handle()`, then sends the response.

## [0.0.1] - 2026-04-03

### Added

- Initial public release of **Vortex** (`vortexphp/framework`).
- **Application core**: `Application`, `Container`, `AppContext` for bootstrapping and dependency injection.
- **HTTP**: `Kernel`, `Request`, `Response`, `Session`, `Csrf`, `TrustProxies`, `ErrorRenderer`, `UploadedFile`.
- **Routing**: `Router`, `Route`, `RouteDiscovery`.
- **Database**: `Connection`, `DB`, `Model`, `QueryBuilder` (PDO).
- **Views**: Twig integration via `View`, `Factory`, and `AppTwigExtension`.
- **Mail**: `MailFactory`, `SmtpMailer`, `NativeMailer`, `NullMailer`, `LogMailer`, `MailMessage`, encoding helpers.
- **Cache**: `Cache` contract, `FileCache`, `NullCache`, `CacheFactory`.
- **Config**: `Repository` for configuration access.
- **Console**: `ConsoleApplication`, `ServeCommand`, `SmokeCommand`, `DoctorCommand`, `DbCheckCommand`, `MigrateCommand`.
- **Events**: `EventBus`, `Dispatcher`, `DispatcherFactory`.
- **I18n**: `Translator` and `helpers.php` autoloaded helpers.
- **Validation**: `Validator`, `ValidationResult`.
- **Crypto**: `Password`, `Crypt`, `SecurityHelp`.
- **Support**: `Env`, `Log`, and string/array/URL/JSON/HTML/date/number/path helpers.
- **Files**: `LocalPublicStorage` for public disk paths.
- **Contracts**: `Cache`, `Mailer`, `Middleware`.
- PHPUnit test suite under `tests/`.

[0.3.0]: https://github.com/vortexphp/framework/releases/tag/v0.3.0
[0.2.0]: https://github.com/vortexphp/framework/releases/tag/v0.2.0
[0.1.0]: https://github.com/vortexphp/framework/releases/tag/v0.1.0
[0.0.1]: https://github.com/vortexphp/framework/releases/tag/v0.0.1
