<?php

declare(strict_types=1);

namespace Vortex\Console\Commands;

use Vortex\Console\Command;
use Vortex\Console\Input;
use Vortex\Console\Term;
use Vortex\Package\PackageRegistry;

/**
 * Copies {@see \Vortex\Package\Package::publicAssets()} from registered packages into {@code public/}.
 */
final class PublishAssetsCommand extends Command
{
    public function name(): string
    {
        return 'publish:assets';
    }

    public function description(): string
    {
        return 'Publish package public assets (JS/CSS/etc.) from vortex packages into public/.';
    }

    protected function execute(Input $input): int
    {
        $lines = PackageRegistry::publishPublicAssets($this->basePath());
        if ($lines === []) {
            fwrite(STDOUT, Term::style('2', 'No public assets registered by configured packages.') . "\n");

            return 0;
        }
        foreach ($lines as $line) {
            fwrite(STDOUT, $line . "\n");
        }

        return 0;
    }
}
