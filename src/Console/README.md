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

Built-in commands include **`migrate`**, **`migrate:down`**, **`make:migration`**, **`make:command`**, **`repl`** (PHP eval with `$app` / `$c`; requires **`app.debug`** or **`--force`**), **`queue:work`**, **`queue:failed`**, **`queue:retry`** (see `src/Queue/README.md`), **`schedule:run`** (see `src/Schedule/README.md`), **`doctor`**, **`serve`**, and others — run `php your-entrypoint help` for the list.

- **`make:migration <name>`** — creates `YYYYMMDDHHMMSS_<name>.php` under the migrations directory (from **`config/paths.php`** or default **`db/migrations`**).
- **`make:command <name>`** — creates **`app/Console/Commands/{Name}Command.php`**; register the class from **`app/Routes/*Console.php`** as in the example below.

## Register from console routes

`app/Routes/AppConsole.php`:

```php
<?php

use App\Console\HelloCommand;
use Vortex\Console\ConsoleApplication;

return static function (ConsoleApplication $app): void {
    $app->register(new HelloCommand($app->basePath()));
};
```
