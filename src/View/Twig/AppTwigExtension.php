<?php

declare(strict_types=1);

namespace Vortex\View\Twig;

use Vortex\Http\Session;
use Vortex\Support\Benchmark;
use Vortex\Support\HtmlHelp;
use Vortex\Support\UrlHelp;
use Twig\Extension\AbstractExtension;
use Twig\Markup;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\TwigTest;

final class AppTwigExtension extends AbstractExtension
{
    public function getTests(): array
    {
        return [
            new TwigTest('string', static fn (mixed $value): bool => is_string($value)),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('trans', static function (string $key, array $replace = []): string {
                return \trans($key, $replace);
            }),
            new TwigFunction('public_url', static function (string $relative): string {
                return \public_url($relative);
            }),
            new TwigFunction('url_query', static function (string $path, array $query = []): string {
                return UrlHelp::withQuery($path, $query);
            }),
            new TwigFunction('route', static function (string $name, array $params = []): string {
                return \route($name, $params);
            }),
            new TwigFunction('server_now', static fn (): string => date('Y-m-d H:i:s')),
            new TwigFunction('benchmark_ms', static function (string $name = 'request', int $precision = 2): float {
                if (! Benchmark::has($name)) {
                    return 0.0;
                }

                return Benchmark::elapsedMs($name, $precision);
            }),
            new TwigFunction('session_flash', static function (string $key): mixed {
                return Session::flash($key);
            }),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('excerpt_html', [HtmlHelp::class, 'excerpt']),
            new TwigFilter('paragraphs', [self::class, 'paragraphs']),
            new TwigFilter('nl2br_e', static function (?string $value): Markup {
                $safe = htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

                return new Markup(nl2br($safe, false), 'UTF-8');
            }, ['is_safe' => ['html']]),
        ];
    }

    /**
     * @return list<string>
     */
    public static function paragraphs(?string $body): array
    {
        if ($body === null || $body === '') {
            return [];
        }

        $parts = preg_split('/\R/', $body) ?: [];
        $out = [];
        foreach ($parts as $p) {
            $t = trim((string) $p);
            if ($t !== '') {
                $out[] = $t;
            }
        }

        return $out;
    }
}
