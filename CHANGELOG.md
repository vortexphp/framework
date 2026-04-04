# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- **Polymorphic ORM:** **`Model::morphTo()`**, **`morphMany()`**, **`morphOne()`**; **`Relation::morphTo()`**, **`morphMany()`**, **`morphOne()`** for **`eagerRelations()`**. Eager loads batch on `{name}_type` + `{name}_id`; nested paths after **`morphTo`** run per concrete **`Model`** class.
- **Cursor pagination (API):** **`Vortex\Pagination\Cursor`** (encode/decode opaque token), **`CursorPaginator`**, **`InvalidCursorException`**, **`QueryBuilder::cursorPaginate()`** (`next_cursor`, **`has_more`**, **`per_page`**; **`ASC`** / **`DESC`** on a single column). **`CursorPaginator::toApiData()`** for **`Response::apiOk()`** payloads.
- **ORM `hasOne`:** **`Model::hasOne()`**, **`Relation::hasOne()`** eager spec, batched **`with()`** (first related row per parent by **`id`** when duplicates). **`hasMany`**-compatible FK layout on the child.
- **PSR-16 cache:** dependency **`psr/simple-cache`**; **`Vortex\Cache\Psr16Cache`** implements **`Psr\SimpleCache\CacheInterface`** over **`Vortex\Contracts\Cache`**; **`SimpleCacheInvalidArgumentException`** for illegal keys. **`Application`** registers **`CacheInterface`** against the default cache store.
- **CLI codegen:** **`make:migration`** (timestamped file + anonymous **`Migration`** class stub) and **`make:command`** (**`App\Console\Commands\*Command`** skeleton + registration hint).
- **HTTP / routing conventions:** abstract **`Vortex\Http\Controller`** with small **`Response`** helpers; invokable controllers (**`Route::get('/path', MyController::class)`** → **`__invoke`**); **`Router::middleware()`** / **`Route::middleware()`** to attach middleware to the route registered immediately before (class names; de-duplicated).
- **Schema builder:** **`Blueprint`** additions — **`bigInteger`**, **`smallInteger`**, **`decimal`**, **`floatType`**, **`date`**, **`dateTime`**, **`json`**, **`char`**; **`ColumnDefinition::unsigned()`** (MySQL integer family); foreign key **`onUpdate`** actions (**`cascadeOnUpdate`**, **`restrictOnUpdate`**, **`nullOnUpdate`**, **`noActionOnUpdate`**). **`Schema::hasTable()`** for SQLite / MySQL / PostgreSQL.
- **Testing:** **`Vortex\Testing\KernelBrowser`** — **`boot()`**, **`get()`** / **`post()`** / **`postJson()`** / **`request()`**, **`decodeJson()`**, **`resetRequestContext()`** for in-process kernel tests. **`Container::has()`** reports explicit bindings or instances.
- **ORM relation polish:** **`Vortex\Database\Relation`** — **`belongsTo()`**, **`hasMany()`**, **`belongsToMany()`** return **`eagerRelations()`** spec arrays. **`Model::load()`** eager-loads relations on an already-fetched instance (dot paths supported). **`QueryBuilder::eagerLoadOnto()`** runs the same batched loader on a list of models using the builder’s **`with()`** paths.
- **JSON body shape validation:** **`JsonShape::validate()`** for structural/type checks; **`JsonShape::object()`** for nested objects (errors **`parent.child`**); **`JsonShape::listOf()`** / **`listOfObjects()`** for lists of objects (errors **`parent.index.field`**); **`JsonShape::listOfPrimitive()`** for typed primitive lists (errors **`parent.index`**; element spec **`?type`** allows **`null`** entries). **`Request::bodyShapeResponse()`** returns **`422`** via **`validationFailed()`** when the body does not match. **Breaking:** only **`JsonShape::object([...])`** defines nesting; raw associative arrays as specs are rejected. Not JSON Schema; supports optional fields (`?type`) and types **`string`**, **`int`**, **`float`**, **`bool`**, **`number`**, **`array`**, **`list`**, **`object`**.
- **REST resource routing:** **`Router::resource()`** / **`Route::resource()`** — registers **`index`**, **`store`**, **`show`**, **`update`**, **`destroy`**; default **`except`** **`create`** / **`edit`**. Named routes (**`photos.index`**, …); optional name prefix; shared **`middleware`**. **`parameter`** overrides singular placeholder (e.g. **`categories`** → **`{category}`**).
- **HTTP JSON API helpers:** **`Response::apiOk()`** / **`Response::apiError()`** for stable success and error envelopes; abstract **`JsonResource`** with **`toArray()`**, **`toResponse()`**, **`collect()`**, and **`collectionResponse()`**; **`Response::validationFailed()`** maps **`ValidationResult`** to **`422`** / **`validation_failed`** plus **`errors`**. **`Request::validationResponse()`** / **`bodyValidationResponse()`** run **`Validator::make`** and return that response when invalid. **`Request::splitVersionedPath()`**, **`apiVersionFromHeaders()`**, **`resolvedApiVersion()`**, **`matchesApiVersion()`**, **`withPath()`** for **`/v1/...`** and header-based versions. **`ErrorRenderer`** JSON **`notFound`** uses **`Response::notFound()`**; 500 JSON uses **`apiError()`**.
- **ORM eager loading:** **`Model::eagerRelations()`** maps relation method names to **`belongsTo`**, **`hasMany`**, or **`belongsToMany`** specs so **`QueryBuilder::with()`** batches related queries. Nested relations use dot paths (e.g. **`author.country`**). Without a map entry, **`with()`** still resolves by calling the relation method on each model. Invalid spec entries throw **`InvalidArgumentException`**.
- **Route model binding:** **`Router::model($parameter, $modelClass, $column = 'id')`** and **`Router::bind($parameter, Closure $resolver)`**; **`Route::model`** / **`Route::bind`** delegate to the active router. Resolvers run before the action; missing model or resolver returning **`null`** yields **`ErrorRenderer::notFound()`** (404). Model class must extend **`Model`**.
- **Model global scopes:** **`Model::addGlobalScope()`** registers named callbacks applied when building **`query()`**; **`QueryBuilder::withoutGlobalScope()`** / **`withoutGlobalScopes()`**; **`all()`** and **`find()`** use **`query()`** so scopes apply (breaking if you relied on unscoped direct SQL).
- **Model soft deletes:** **`$softDeletes`**, **`$deletedAtColumn`**; **`find()`** / **`all()`** / **`QueryBuilder`** exclude trashed by default; **`withTrashed()`**, **`onlyTrashed()`**; instance **`delete()`** / **`restore()`** / **`forceDelete()`**; mass soft delete via query **`delete()`**; **`onlyTrashed()->delete()`** hard-deletes. **`updateRecord()`** adds **`deleted_at IS NULL`** when soft deletes are enabled.
- **Model casts:** **`protected static array $casts`** on **`Model`** (`int`, `float`, `bool`, `string`, `json`/`array`, `datetime`); applied in **`fromRow()`** and when persisting via **`gatherFillableFromInstance()`** / **`updateRecord()`**.
- **Model observers:** **`Model::observe()`** registers handlers per model class; lifecycle hooks **`saving`**, **`creating`**, **`updating`**, **`deleting`**, **`saved`**, **`created`**, **`updated`**, **`deleted`** run around **`create()`**, **`save()`**, and **`delete()`**. **`Model::forgetRegisteredObservers()`** for tests. **Breaking / behavior:** **`create()`** and new-record **`save()`** share **`performInsert`** (same events); when **`$fillable` is empty**, attributes are taken from all public/dynamic instance properties for persistence (omit unset **`id`** on insert).
- **Queue:** `Vortex\Queue\Contracts\QueueDriver`; **`Vortex\Queue\RedisQueue`** when `queue.driver` is `redis` (ready list + delayed ZSET + JSON envelope; configure `queue.redis`); shared **`Vortex\Support\PhpRedisConnect`** for phpredis. **Breaking:** `DatabaseQueue::delete` / `::release` take `ReservedJob`; `ReservedJob` includes the queue name for Redis retries.
- **Cache:** `redis` driver in `config/cache.php` **`stores`** map (`RedisCache`, phpredis via **`PhpRedisConnect`**); requires **ext-redis**. Values are PHP-serialized; `clear()` uses `SCAN` + `DEL` for the key prefix. **`memcached`** driver (`MemcachedCache`, **`PhpMemcachedConnect`**); requires **ext-memcached**; `clear()` bumps a generation token (no pool-wide flush). **`add($key, $value, $ttlSeconds): bool`** on **`Vortex\Contracts\Cache`** (NX; `RedisCache` uses `SET` `NX` `EX`; **`NullCache::add`** always returns true). **`Cache::add()`** on the default store facade.
- **Schedule:** `Vortex\Schedule\Schedule` loads `config/schedule.php` (`tasks` with `cron` + `class`), supports `Schedule::register()` during `Application::boot()`, **`CronExpression::isDue()`** (five fields: `*`, integer, `*/step`, comma lists, hyphen ranges), optional **`without_overlapping`** / **`mutex_ttl`** (`mutex_ttl_seconds`) on tasks and **`mutex_store`** (named cache store) in schedule config for overlap guards via **`Cache::add()`**, CLI **`schedule:run`**, and **`app.timezone`** for “now”. **`Repository::initialized()`** for safe reads when the repository was not booted.
- **Queue:** `Vortex\Queue\Contracts\Job`, `DatabaseQueue` (SQL table + reservation / stale reclaim), static `Vortex\Queue\Queue::push()`, `DatabaseQueue::pushSerialized()`, CLI **`queue:work`**, **`queue:failed`**, **`queue:retry`**, and **`FailedJobStore`** (permanent failures recorded when `queue.failed_jobs_table` is set; empty string disables recording). Config: `queue.table`, `queue.default`, `queue.tries`, `queue.stale_reserve_seconds`, `queue.idle_sleep_ms`, `queue.failed_jobs_table`.
- **`Vortex\Auth\Auth`** session facade: `loginUsingId`, `login` (`Authenticatable`), `logout`, `check`, `guest`, `id`, `user` with optional `resolveUserUsing` callback; `login` / `loginUsingId` accept `$remember` to set a signed remember cookie; `logout` clears the remember cookie.
- **`Vortex\Auth\Gate`** — `define()`, `policy()`, `allows()`, `denies()`, `authorize()`; **`AuthorizationException`** for denied `authorize()` (rendered as 403 by **`ErrorRenderer`** without the full exception log path).
- **`Vortex\Auth\RememberCookie`** — signed payload (requires **`APP_KEY`**); **`Vortex\Auth\Middleware\RememberFromCookie`** restores session from the cookie.
- **`Vortex\Auth\PasswordResetBroker`** — hashed single-use tokens in SQL with configurable table name and TTL; `issueToken`, `tokenValid`, `verifyAndConsume`, `purgeExpired`.
- **Middleware:** **`Vortex\Auth\Middleware\Authenticate`** (JSON 401 or redirect to `auth.login_path`); abstract **`AuthorizeAbility`** (403 when `Gate::denies`).
- **`Vortex\Auth\AuthConfig`** reads `auth.login_path`, `auth.remember_cookie`, `auth.remember_seconds`, `auth.cookie_secure`, `auth.cookie_samesite` when the config repository is available.
- Twig **`gate_allows`** (optional second argument for policy context; one-argument calls delegate to `Gate::allows($ability)` only).

### Changed

- **Breaking:** When **`Request::wantsJson()`**, **`Response::error()`** (including **`notFound()`**, **`forbidden()`**, **`unauthorized()`**) JSON now always includes **`error`** (machine-readable code; shortcuts set values such as **`not_found`**, default **`http_error`**) with **`ok`** and **`message`**.

## [0.7.0] - 2026-04-03

### Added

- Fluent validation rule builder **`Vortex\Validation\Rule`** with inline per-rule message support; **`Validator::make()`** now accepts rule objects.
- Fluent response/session flash helpers: **`Response::with()`**, **`withMany()`**, **`withErrors()`**, **`withInput()`**, and session batch helpers **`Session::flashMany()`** / **`flashPutMany()`**.
- Request-aware response shortcuts: **`Response::error()`**, **`notFound()`**, **`forbidden()`**, and **`unauthorized()`** that auto-select HTML or JSON output.

### Changed

- **`Request::wantsJson()`** now treats **`X-Requested-With: XMLHttpRequest`** as JSON-preferring requests.
- **Breaking:** **`composer.lock`** is no longer tracked in the repository and is now ignored.

## [0.6.0] - 2026-04-03

### Added

- Model relation helpers in **`Vortex\Database\Model`**: **`belongsTo`**, **`hasMany`**, and **`belongsToMany`**.
- Query builder feature expansion in **`Vortex\Database\QueryBuilder`**: **`select`**, **`with`** (eager loading), joins, grouped/or where clauses, **`pluck`**, **`value`**, bulk **`update`**, **`delete`**, and raw result methods.
- Configurable Twig extension registration via **`app.twig_extensions`** in app config; extensions are injected through **`Vortex\View\Factory`**.
- Twig function **`benchmark_ms()`** for reading named benchmark timings in views.

### Changed

- **Breaking:** Migration classes now extend abstract **`Vortex\Database\Schema\Migration`** and implement parameterless **`up()`**/**`down()`** methods.
- **Breaking:** Migration IDs are now resolved from migration filenames instead of **`Migration::id()`**.
- Schema builder now exposes static entrypoints (**`Schema::create`**, **`Schema::table`**, **`Schema::dropIfExists`**) with connection scoping handled by the migrator.
- Console command execution flow is unified under the base **`Vortex\Console\Command`** lifecycle.
- Default model table-name resolution now pluralizes snake_case model names; explicit **`protected static ?string $table`** overrides are supported.

## [0.5.0] - 2026-04-03

### Added

- Optional **`config/paths.php`** — configure **`migrations`** (migration class directory) relative to the project root. **`Vortex\Support\AppPaths`** resolves it and CLI commands use the same rules.

### Changed

- **Breaking:** Console commands (`migrate`, `migrate:down`, `db-check`) now boot the app container directly via **`Application::boot()`**; startup container files are no longer required.

## [0.4.0] - 2026-04-03

### Changed

- **Breaking:** Migration PHP classes are loaded from **`db/migrations/`** (was **`database/migrations/`**).
- **Breaking:** Console commands that load the app container (`migrate`, `migrate:down`, `db-check`) require **`startup/app.php`** (was **`bootstrap/app.php`**).
- PHPDoc and uninitialized-facade errors now refer to **`Application::boot()`** instead of the word “bootstrap” where that meant the app entrypoint.

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

[0.7.0]: https://github.com/vortexphp/framework/releases/tag/v0.7.0
[0.6.0]: https://github.com/vortexphp/framework/releases/tag/v0.6.0
[0.5.0]: https://github.com/vortexphp/framework/releases/tag/v0.5.0
[0.4.0]: https://github.com/vortexphp/framework/releases/tag/v0.4.0
[0.3.0]: https://github.com/vortexphp/framework/releases/tag/v0.3.0
[0.2.0]: https://github.com/vortexphp/framework/releases/tag/v0.2.0
[0.1.0]: https://github.com/vortexphp/framework/releases/tag/v0.1.0
[0.0.1]: https://github.com/vortexphp/framework/releases/tag/v0.0.1
