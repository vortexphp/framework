<?php

declare(strict_types=1);

namespace Vortex\Console;

use Vortex\Application;

abstract class Command
{
    private string $basePath = '';
    private ?Application $application = null;

    final public function setBasePath(string $basePath): void
    {
        $this->basePath = rtrim($basePath, '/');
    }

    public function name(): string
    {
        return self::deriveName(static::class);
    }

    public function basePath(): string
    {
        if ($this->basePath === '') {
            throw new \LogicException('Command base path is not configured.');
        }

        return $this->basePath;
    }

    final public function run(Input $input): int
    {
        if ($this->shouldBootApplication()) {
            $this->application ??= $this->bootApplication();
        }

        return $this->execute($input);
    }

    abstract public function description(): string;

    abstract protected function execute(Input $input): int;

    protected function shouldBootApplication(): bool
    {
        return false;
    }

    protected function app(): Application
    {
        return $this->application ??= $this->bootApplication();
    }

    protected function line(string $message = ''): void
    {
        $this->writeToStderr($message);
    }

    protected function info(string $message): void
    {
        $this->writeToStderr(Term::style('1;32', $message));
    }

    protected function warning(string $message): void
    {
        $this->writeToStderr(Term::style('1;33', $message));
    }

    protected function error(string $message): void
    {
        $this->writeToStderr(Term::style('1;31', $message));
    }

    private function bootApplication(): Application
    {
        return Application::boot($this->basePath());
    }

    private function writeToStderr(string $message): void
    {
        fwrite(STDERR, $message . "\n");
    }

    private static function deriveName(string $class): string
    {
        $short = preg_replace('/^.*\\\\/', '', $class) ?? $class;
        $short = preg_replace('/Command$/', '', $short) ?? $short;
        $snake = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $short));
        $parts = array_values(array_filter(explode('_', $snake), static fn (string $part): bool => $part !== ''));

        if ($parts === []) {
            return 'command';
        }

        if (count($parts) === 1) {
            return $parts[0];
        }

        return $parts[0] . ':' . implode('-', array_slice($parts, 1));
    }
}
