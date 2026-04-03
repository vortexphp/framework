<?php

declare(strict_types=1);

namespace Vortex\I18n;

final class Translator
{
    private static ?self $instance = null;

    /** @var array<string, array<string, mixed>> */
    private array $loaded = [];

    /**
     * @param list<string> $supportedLocales
     */
    public function __construct(
        private readonly string $langPath,
        private string $locale,
        private readonly string $fallbackLocale,
        private readonly array $supportedLocales,
    ) {
    }

    public static function setInstance(self $translator): void
    {
        self::$instance = $translator;
    }

    public static function forgetInstance(): void
    {
        self::$instance = null;
    }

    private static function resolved(): self
    {
        if (self::$instance === null) {
            throw new \RuntimeException('Translator is not initialized; call Translator::setInstance() during Application::boot().');
        }

        return self::$instance;
    }

    public static function locale(): string
    {
        return self::resolved()->readLocale();
    }

    /**
     * @return list<string>
     */
    public static function supportedLocales(): array
    {
        return self::resolved()->readSupportedLocales();
    }

    public static function setLocale(string $locale): void
    {
        self::resolved()->assignLocale($locale);
    }

    /**
     * Dot key (e.g. {@code auth.login.title}). Missing keys fall back to the fallback locale, then the key itself.
     *
     * @param array<string, string|int|float> $replace placeholders {@code :name} in the string
     */
    public static function get(string $key, array $replace = []): string
    {
        return self::resolved()->translateLine($key, $replace);
    }

    private function readLocale(): string
    {
        return $this->locale;
    }

    /**
     * @return list<string>
     */
    private function readSupportedLocales(): array
    {
        return $this->supportedLocales;
    }

    private function assignLocale(string $locale): void
    {
        $this->locale = in_array($locale, $this->supportedLocales, true)
            ? $locale
            : $this->fallbackLocale;
    }

    /**
     * @param array<string, string|int|float> $replace
     */
    private function translateLine(string $key, array $replace): string
    {
        $line = $this->line($key, $this->locale)
            ?? $this->line($key, $this->fallbackLocale);

        if (! is_string($line)) {
            return $key;
        }

        foreach ($replace as $k => $v) {
            $line = str_replace(':' . ltrim((string) $k, ':'), (string) $v, $line);
        }

        return $line;
    }

    private function line(string $key, string $locale): ?string
    {
        $value = $this->dig($this->dictionary($locale), $key);

        return is_string($value) ? $value : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function dictionary(string $locale): array
    {
        if (isset($this->loaded[$locale])) {
            return $this->loaded[$locale];
        }

        $path = $this->langPath . '/' . $locale . '.php';
        if (! is_file($path)) {
            $this->loaded[$locale] = [];

            return [];
        }

        /** @var mixed $data */
        $data = require $path;
        $this->loaded[$locale] = is_array($data) ? $data : [];

        return $this->loaded[$locale];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function dig(array $data, string $key): mixed
    {
        $cursor = $data;
        foreach (explode('.', $key) as $segment) {
            if ($segment === '' || ! is_array($cursor) || ! array_key_exists($segment, $cursor)) {
                return null;
            }
            $cursor = $cursor[$segment];
        }

        return $cursor;
    }
}
