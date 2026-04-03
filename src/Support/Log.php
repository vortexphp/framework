<?php

declare(strict_types=1);

namespace Vortex\Support;

use JsonException;
use RuntimeException;
use Throwable;

/**
 * File logger for **`storage/logs/app.log`**. Call {@see setBasePath()} once during bootstrap
 * (before {@see exception()}, {@see info()}, etc.).
 */
final class Log
{
    private static ?string $basePath = null;

    public static function setBasePath(string $basePath): void
    {
        self::$basePath = rtrim($basePath, '/');
    }

    /**
     * @internal Testing only.
     */
    public static function reset(): void
    {
        self::$basePath = null;
    }

    public static function emergency(string $message, array $context = []): void
    {
        self::write('EMERGENCY', $message, $context);
    }

    public static function alert(string $message, array $context = []): void
    {
        self::write('ALERT', $message, $context);
    }

    public static function critical(string $message, array $context = []): void
    {
        self::write('CRITICAL', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::write('WARNING', $message, $context);
    }

    public static function notice(string $message, array $context = []): void
    {
        self::write('NOTICE', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    public static function debug(string $message, array $context = []): void
    {
        self::write('DEBUG', $message, $context);
    }

    /**
     * @param 'emergency'|'alert'|'critical'|'error'|'warning'|'notice'|'info'|'debug' $level
     */
    public static function log(string $level, string $message, array $context = []): void
    {
        self::write(strtoupper($level), $message, $context);
    }

    public static function exception(Throwable $e): void
    {
        $line = sprintf(
            "[%s] EXCEPTION %s: %s in %s:%d\n%s\n",
            date('c'),
            $e::class,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString(),
        );
        self::appendRaw($line);
    }

    private static function basePathOrFail(): string
    {
        if (self::$basePath === null || self::$basePath === '') {
            throw new RuntimeException('Log::setBasePath() must be called before writing logs.');
        }

        return self::$basePath;
    }

    private static function write(string $level, string $message, array $context): void
    {
        $suffix = self::formatContext($context);
        $line = sprintf("[%s] %s %s%s\n", date('c'), $level, $message, $suffix);
        self::appendRaw($line);
    }

    private static function formatContext(array $context): string
    {
        if ($context === []) {
            return '';
        }

        try {
            return ' ' . JsonHelp::encode($context);
        } catch (JsonException) {
            return ' [context: not serializable]';
        }
    }

    private static function appendRaw(string $line): void
    {
        $base = self::basePathOrFail();
        $dir = $base . '/storage/logs';
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $file = $dir . '/app.log';
        error_log($line, 3, $file);
    }
}
