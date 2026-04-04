<?php

declare(strict_types=1);

namespace Vortex\Console;

/**
 * argv[0] = script, argv[1] = command name, argv[2..] = tokens.
 *
 * Parsing rules (tokens only):
 * - Positional arguments are non-option tokens in order.
 * - Long options: {@code --name=value}, {@code --name value}, or {@code --name} (boolean true).
 * - Short option clusters: {@code -abc} sets flags {@code a}, {@code b}, {@code c} when the rest is ASCII letters only;
 *   otherwise the token is treated as a positional argument (e.g. {@code -1}).
 * - {@code --} ends option parsing; everything after is positional.
 */
final class Input
{
    /** @var list<string>|null */
    private ?array $parsedArguments = null;

    /** @var array<string, string|bool>|null */
    private ?array $parsedOptions = null;

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
     * Positional arguments (after option parsing).
     *
     * @return list<string>
     */
    public function arguments(): array
    {
        $this->parse();

        return $this->parsedArguments;
    }

    /**
     * Option values: string for {@code --k=v} / {@code --k v}; boolean true for present flags.
     *
     * @return array<string, string|bool>
     */
    public function options(): array
    {
        $this->parse();

        return $this->parsedOptions;
    }

    public function argument(int $index, ?string $default = null): ?string
    {
        return $this->arguments()[$index] ?? $default;
    }

    public function hasOption(string $name): bool
    {
        return array_key_exists($name, $this->options());
    }

    /**
     * @param string|bool|null $default
     * @return string|bool|null
     */
    public function option(string $name, string|bool|null $default = null): string|bool|null
    {
        if (! $this->hasOption($name)) {
            return $default;
        }

        return $this->options()[$name];
    }

    public function flag(string $name): bool
    {
        return $this->option($name, false) === true;
    }

    /**
     * @return list<string>
     */
    public function argv(): array
    {
        return $this->argv;
    }

    private function parse(): void
    {
        if ($this->parsedArguments !== null) {
            return;
        }

        $tokens = $this->tokens();
        $args = [];
        $opts = [];
        $i = 0;
        $n = count($tokens);

        while ($i < $n) {
            $t = $tokens[$i];
            if ($t === '--') {
                $args = [...$args, ...array_slice($tokens, $i + 1)];
                break;
            }
            if ($t === '-') {
                $args[] = $t;
                ++$i;
                continue;
            }
            if (str_starts_with($t, '--')) {
                $body = substr($t, 2);
                if ($body === '') {
                    ++$i;
                    continue;
                }
                if (str_contains($body, '=')) {
                    [$k, $v] = explode('=', $body, 2);
                    $opts[$k] = $v;
                    ++$i;
                    continue;
                }
                $next = $tokens[$i + 1] ?? null;
                if ($next !== null && $next !== '' && ! str_starts_with($next, '-') && $next !== '--') {
                    $opts[$body] = $next;
                    $i += 2;
                    continue;
                }
                $opts[$body] = true;
                ++$i;
                continue;
            }
            if (str_starts_with($t, '-') && strlen($t) > 1) {
                $rest = substr($t, 1);
                if (preg_match('/^[a-zA-Z]+$/', $rest) === 1) {
                    foreach (str_split($rest) as $ch) {
                        $opts[$ch] = true;
                    }
                    ++$i;
                    continue;
                }
                $args[] = $t;
                ++$i;
                continue;
            }

            $args[] = $t;
            ++$i;
        }

        $this->parsedArguments = $args;
        $this->parsedOptions = $opts;
    }
}
