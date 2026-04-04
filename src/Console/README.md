# Console Module

CLI entrypoint, command base class, and built-in commands.

## Example custom command

```php
<?php

namespace App\Console;

use Vortex\Console\Command;
use Vortex\Console\Input;

final class HelloCommand extends Command
{
    public function description(): string
    {
        return 'Print hello';
    }

    protected function execute(Input $input): int
    {
        $this->info('Hello from Vortex CLI');
        return 0;
    }
}
```

## Arguments and options

**`Input`** (everything after the command name) exposes:

- **`arguments()`** — positional tokens (options stripped; see **`--`** below).
- **`argument(int $index, ?string $default = null)`** — one positional.
- **`options()`** — map of long names (and single-letter short flags) to **`string`** or boolean **`true`**.
- **`option(string $name, …)`** / **`hasOption()`** / **`flag()`** — read options; flags are **`true`** when present without a value.

Supported forms: **`--name=value`**, **`--name value`**, **`--flag`**, short clusters **`-abc`** (letters only; tokens like **`-1`** stay positional), and **`--`** so the rest are arguments even if they look like options. If the token after a long option does not start with **`-`**, it becomes that option’s value—use **`--`** in between when you mean a boolean flag plus a separate positional.

Built-in commands include **`migrate`**, **`migrate:down`**, **`make:migration`**, **`make:model`**, **`make:command`**, **`repl`** (PHP eval with `$app` / `$c`; requires **`app.debug`** or **`--force`**), **`queue:work`**, **`queue:failed`**, **`queue:retry`** (see `src/Queue/README.md`), **`schedule:run`** (see `src/Schedule/README.md`), **`doctor`**, **`serve`**, and others — run `php your-entrypoint help` for the list.

Codegen uses **`*.stub`** files under **`src/Console/stubs/`** (`{{PLACEHOLDER}}` substitution via **`Vortex\Console\Stub`**). Apps may fork stubs by contributing to the framework or copying patterns locally.

- **`make:migration <name>`** — creates `YYYYMMDDHHMMSS_<name>.php` under the migrations directory (from **`config/paths.php`** or default **`db/migrations`**).
- **`make:model <Name> [--table=…]`** — creates **`app/Models/{Name}.php`** (namespace **`App\Models`**; **`config/paths.php`** key **`models`** overrides the folder). Omit **`--table`** to use **`Model::table()`** inference from the class name.
- **`make:command <name>`** — creates **`app/Console/Commands/{Name}Command.php`**; register the class from **`app/Routes/*Console.php`** as in the example below.

## Register from console routes

**`ConsoleApplication::register()`** assigns the project root via **`Command::setBasePath()`** (use **`$command->basePath()`** in **`execute()`**). No need to pass the path into your command’s constructor.

`app/Routes/AppConsole.php`:

```php
<?php

use App\Console\HelloCommand;
use Vortex\Console\ConsoleApplication;

return static function (ConsoleApplication $app): void {
    $app->register(new HelloCommand());
};
```
