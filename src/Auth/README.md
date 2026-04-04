# Auth module

Session authentication built on `auth_user_id` in the active session store, optional signed remember-me cookie, a small authorization gate, and a SQL password-reset token broker.

## Config (`config` repository)

Optional keys (see **`AuthConfig`**):

| Key | Default | Purpose |
|-----|---------|---------|
| `auth.login_path` | `/login` | Redirect target for **`Authenticate`** when the client is not JSON-preferring. |
| `auth.remember_cookie` | `remember_web` | Remember cookie name. |
| `auth.remember_seconds` | `1209600` (14 days) | Remember cookie lifetime (minimum 60). |
| `auth.cookie_secure` | `false` | Set on the remember cookie when true. |
| `auth.cookie_samesite` | `Lax` | SameSite attribute for the remember cookie. |

Remember cookies require **`APP_KEY`** (used by **`Crypt`** for signing).

## Login and logout

```php
<?php

use Vortex\Auth\Auth;

Auth::loginUsingId(42);
Auth::loginUsingId(42, remember: true);

// or: Auth::login($user); Auth::login($user, remember: true); // $user implements Authenticatable

Auth::logout(); // clears remember cookie + session auth (see Session::flushAuth)
```

## Guards

```php
if (Auth::check()) {
    $id = Auth::id();
}

if (Auth::guest()) {
    return Response::redirect('/login');
}
```

## Resolve the user record

Register once during `Application::boot(..., configure)`:

```php
Auth::resolveUserUsing(static function (int $id): ?User {
    return User::find($id);
});
```

Then:

```php
$user = Auth::user(); // null if guest or resolver missing / returns null
```

## Gate and policies

Abilities are callables `(mixed $user, mixed ...$args): bool`. The current user may be null.

```php
use Vortex\Auth\Gate;

Gate::define('manage-dashboard', static fn (mixed $user): bool => $user !== null);

Gate::allows('manage-dashboard');
Gate::authorize('manage-dashboard'); // AuthorizationException if denied
```

Register a policy class for a model; method names match abilities (e.g. `update` for `Gate::allows('update', $post)`):

```php
Gate::policy(Post::class, PostPolicy::class);
```

Policies are constructed from the container (`AppContext::container()->make(...)`).

## HTTP middleware

Register class names in **`app.middleware`** (see **`Kernel::handle`**). Example order: session-related middleware first, then remember, then the rest.

- **`Vortex\Auth\Middleware\RememberFromCookie`** — if guest, validate remember cookie and call `Auth::loginUsingId` without remember, then refresh the cookie.
- **`Vortex\Auth\Middleware\Authenticate`** — guests get `Response::unauthorized()` for JSON-preferring requests or a redirect to `auth.login_path`.
- **`Vortex\Auth\Middleware\AuthorizeAbility`** — extend, implement `ability(): string`, optionally `arguments(Request $request): array`; denied users get 403.

Per-route middleware: pass middleware class names as the last argument to **`Route::get`**, **`Route::post`**, etc.

## Password reset tokens

Use **`PasswordResetBroker`** with your **`Connection`**. The broker stores a **SHA-256** hash of the opaque token; **`issueToken`** returns the plain token to put in a link or email.

Example table (adjust types for your engine):

```sql
CREATE TABLE password_reset_tokens (
    email VARCHAR(255) NOT NULL,
    token VARCHAR(64) NOT NULL,
    created_at INTEGER NOT NULL,
    PRIMARY KEY (email)
);
```

Run **`purgeExpired()`** periodically (console command or scheduler) using the broker’s TTL.

Flow for the app: collect email → `issueToken` → send mail with token → validate with `tokenValid` on the reset form → on submit call `verifyAndConsume` once, then set the new password.

## Twig

- `auth_check()` — same as `Auth::check()`
- `auth_id()` — same as `Auth::id()`
- `auth_user()` — same as `Auth::user()`
- `gate_allows('ability')` or `gate_allows('ability', model)` — maps to `Gate::allows` (single-argument form does not pass a context object)
