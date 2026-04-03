<?php

declare(strict_types=1);

namespace Vortex\Support;

use Throwable;

final class Log
{
    public static function exception(Throwable $e, string $basePath): void
    {
        $dir = rtrim($basePath, '/') . '/storage/logs';
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $file = $dir . '/app.log';
        $line = sprintf(
            "[%s] %s: %s in %s:%d\n%s\n",
            date('c'),
            $e::class,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString(),
        );
        error_log($line, 3, $file);
    }
}
