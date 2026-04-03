<?php

declare(strict_types=1);

namespace Vortex\View;

use InvalidArgumentException;
use Vortex\Http\Response;
use Vortex\View\Twig\AppTwigExtension;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * Twig environment and template resolution. Use {@see View} for {@code share}, {@code render}, and {@code html}.
 */
final class Factory
{
    /** @var array<string, mixed> */
    private array $shared = [];

    private ?Environment $twig = null;

    public function __construct(
        private readonly string $templatesPath,
        private readonly bool $debug = false,
        private readonly ?string $twigCachePath = null,
    ) {
    }

    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $name, array $data = []): string
    {
        $relative = str_replace('.', '/', $name) . '.twig';
        $path = $this->templatesPath . '/' . $relative;
        if (! is_file($path)) {
            throw new InvalidArgumentException("Template [{$name}] not found.");
        }

        return $this->environment()->render($relative, array_merge($this->shared, $data));
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string|string[]> $headers
     */
    public function html(string $name, array $data = [], int $status = 200, array $headers = []): Response
    {
        return Response::html($this->render($name, $data), $status, $headers);
    }

    private function environment(): Environment
    {
        if ($this->twig !== null) {
            return $this->twig;
        }

        $loader = new FilesystemLoader($this->templatesPath);
        $cache = false;
        if (! $this->debug && $this->twigCachePath !== null && $this->twigCachePath !== '') {
            if (! is_dir($this->twigCachePath)) {
                mkdir($this->twigCachePath, 0755, true);
            }
            $cache = $this->twigCachePath;
        }

        $this->twig = new Environment($loader, [
            'cache' => $cache,
            'debug' => $this->debug,
            'strict_variables' => $this->debug,
        ]);

        if ($this->debug) {
            $this->twig->addExtension(new \Twig\Extension\DebugExtension());
        }

        $this->twig->addExtension(new AppTwigExtension());

        return $this->twig;
    }
}
