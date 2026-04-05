<?php

declare(strict_types=1);

namespace Vortex\View;

use InvalidArgumentException;
use Vortex\Http\Response;
use Vortex\View\Twig\AppTwigExtension;
use Twig\Environment;
use Twig\Extension\ExtensionInterface;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\Loader\FilesystemLoader;

/**
 * Twig environment and template resolution. Use {@see View} for {@code share}, {@code render}, and {@code html}.
 */
final class Factory
{
    /** @var array<string, mixed> */
    private array $shared = [];

    /** @var list<ExtensionInterface> */
    private array $extensions = [];

    /** @var list<TwigFilter> */
    private array $filters = [];

    /** @var list<TwigFunction> */
    private array $functions = [];

    private ?Environment $twig = null;

    /** @var list<string> */
    private array $extraTemplatePaths = [];

    public function __construct(
        private readonly string $templatesPath,
        private readonly bool $debug = false,
        private readonly ?string $twigCachePath = null,
    ) {
    }

    /**
     * Extra roots for Twig ({@see FilesystemLoader}) checked after the app {@code resources/views} path.
     * Call before the first render — not after Twig is initialized.
     */
    public function addTemplatePath(string $path): void
    {
        if ($this->twig !== null) {
            throw new \LogicException('Cannot add template paths after Twig has been booted.');
        }
        $path = rtrim(str_replace('\\', '/', $path), '/');
        if ($path !== '') {
            $this->extraTemplatePaths[] = $path;
        }
    }

    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    public function addExtension(ExtensionInterface $extension): void
    {
        if ($this->twig !== null) {
            $this->twig->addExtension($extension);

            return;
        }

        $this->extensions[] = $extension;
    }

    public function addFilter(TwigFilter $filter): void
    {
        if ($this->twig !== null) {
            $this->twig->addFilter($filter);

            return;
        }

        $this->filters[] = $filter;
    }

    public function addFunction(TwigFunction $function): void
    {
        if ($this->twig !== null) {
            $this->twig->addFunction($function);

            return;
        }

        $this->functions[] = $function;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $name, array $data = []): string
    {
        $relative = str_replace('.', '/', $name) . '.twig';
        if (! $this->templateExists($relative)) {
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

        $loader = new FilesystemLoader($this->loaderPathList());
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
        foreach ($this->extensions as $extension) {
            $this->twig->addExtension($extension);
        }
        foreach ($this->filters as $filter) {
            $this->twig->addFilter($filter);
        }
        foreach ($this->functions as $function) {
            $this->twig->addFunction($function);
        }

        return $this->twig;
    }

    /**
     * @return list<string>
     */
    private function loaderPathList(): array
    {
        return array_values(array_merge([rtrim(str_replace('\\', '/', $this->templatesPath), '/')], $this->extraTemplatePaths));
    }

    private function templateExists(string $relative): bool
    {
        foreach ($this->loaderPathList() as $root) {
            if (is_file($root . '/' . $relative)) {
                return true;
            }
        }

        return false;
    }
}
