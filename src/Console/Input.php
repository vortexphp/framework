<?php

declare(strict_types=1);

namespace Vortex\Console;

final class Input
{
    /**
     * @param list<string> $argv
     */
    public function __construct(
        private readonly array $argv,
    ) {
    }

    /**
     * @param list<string> $argv
     */
    public static function fromArgv(array $argv): self
    {
        return new self(array_values($argv));
    }

    public function script(): string
    {
        return $this->argv[0] ?? 'cli';
    }

    public function command(): ?string
    {
        return $this->argv[1] ?? null;
    }

    /**
     * Raw tokens after the command name (argv[2..]).
     *
     * @return list<string>
     */
    public function tokens(): array
    {
        return array_slice($this->argv, 2);
    }

    /**
     * @return list<string>
     */
    public function argv(): array
    {
        return $this->argv;
    }
}
