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
