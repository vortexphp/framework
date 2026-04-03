# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] - 2026-04-03

### Breaking

- **HTTP route files** (`app/Routes/*.php` except `*Console.php`) are **`require`d** and must register routes at the top level with **`Route::get` / `post` / `add`** — no **`return static function (): void { … }`** wrapper. Console route files still **`return callable(ConsoleApplication): void`**.

### Added

- **Routing**: named routes (**`Router::name`**, **`Router::path`**, **`Route::name()`**), global **`route()`** helper and Twig **`route()`**, **`Router::interpolatePattern()`**.
- **HTTP testing**: **`Kernel::handle(Request)`**, **`Request::make()`**, **`Request::normalizePath()`**, **`Response::headers()`**.
- **Rate limiting**: **`RateLimiter`**, **`Middleware\Throttle`** (cache-backed fixed window), **`config/throttle.php`** pattern in apps.
- **`php vortex doctor`**: requires **`ext-mbstring`**; **`doctor --production`** requires non-empty **`APP_KEY`**.

### Changed

- **`Kernel::send()`** applies **`TrustProxies`**, captures the request, then delegates to **`handle()`** before **`send()`** on the response.

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

[0.1.0]: https://github.com/vortexphp/framework/releases/tag/v0.1.0
[0.0.1]: https://github.com/vortexphp/framework/releases/tag/v0.0.1
