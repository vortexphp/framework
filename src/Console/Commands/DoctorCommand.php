<?php

declare(strict_types=1);

namespace Vortex\Console\Commands;

use Vortex\Console\Command;
use Vortex\Console\Input;
use Vortex\Console\Term;
use Vortex\Crypto\SecurityHelp;
use Vortex\Support\Env;
use Vortex\Support\FilesConfigUploadRoots;
use Vortex\Support\PathHelp;

final class DoctorCommand extends Command
{
    public function __construct(
        private readonly string $basePath,
    ) {
        parent::__construct($basePath);
    }

    public function name(): string
    {
        return 'doctor';
    }

    public function description(): string
    {
        return 'Environment checks: PHP, ext-pdo, ext-mbstring, PDO driver, public/, storage/, config/files.php upload dirs. Use --production for .env, APP_DEBUG, APP_URL, APP_KEY, vendor, CSS.';
    }

    protected function execute(Input $input): int
    {
        $base = $this->basePath;
        $failed = false;
        $production = in_array('--production', $input->tokens(), true);

        $title = $production
            ? Term::style('2', ' — §1 hosting') . ' + ' . Term::style('1;33', '§2 production')
            : Term::style('2', ' — checklist §1');
        fwrite(STDERR, "\n " . Term::style('1;36', 'Vortex doctor') . $title . "\n\n");

        // PHP 8.2+
        $ok = PHP_VERSION_ID >= 80200;
        $failed = $this->statusLine($ok, 'PHP ' . PHP_VERSION . ' (need ^8.2)') || $failed;

        $failed = $this->statusLine(extension_loaded('pdo'), 'ext-pdo loaded') || $failed;
        $failed = $this->statusLine(extension_loaded('mbstring'), 'ext-mbstring loaded (required by framework)') || $failed;

        $drivers = [];
        foreach (['pdo_sqlite', 'pdo_mysql', 'pdo_pgsql'] as $ext) {
            if (extension_loaded($ext)) {
                $drivers[] = $ext;
            }
        }
        $driverOk = $drivers !== [];
        $failed = $this->statusLine($driverOk, 'PDO driver: ' . ($driverOk ? implode(', ', $drivers) : 'none (install pdo_sqlite and/or pdo_mysql / pdo_pgsql)')) || $failed;

        $public = $base . '/public';
        $failed = $this->statusLine(is_dir($public), "Directory exists: public/") || $failed;
        $failed = $this->statusLine(is_file($public . '/index.php'), 'File exists: public/index.php') || $failed;

        $storage = $base . '/storage';
        $logs = $storage . '/logs';
        if (! is_dir($logs)) {
            @mkdir($logs, 0775, true);
        }
        $failed = $this->statusLine(is_dir($storage), 'Directory exists: storage/') || $failed;
        $failed = $this->statusLine(is_dir($logs), 'Directory exists: storage/logs/ (created if missing)') || $failed;
        $failed = $this->statusLine(is_writable($storage), 'Writable: storage/') || $failed;
        $failed = $this->statusLine(is_writable($logs), 'Writable: storage/logs/') || $failed;

        $probe = $logs . '/.doctor-write-test';
        $writeOk = @file_put_contents($probe, (string) time()) !== false;
        if ($writeOk) {
            @unlink($probe);
        }
        $failed = $this->statusLine($writeOk, 'Can create/delete file in storage/logs/') || $failed;

        $failed = $this->checkConfiguredUploadDirectories($base, $public, $failed);

        $envPath = $base . '/.env';
        $envOk = is_readable($envPath);
        if ($production) {
            $failed = $this->statusLine($envOk, '.env readable (required with --production)') || $failed;
            if ($envOk) {
                Env::load($envPath);
                $debugOn = filter_var(Env::get('APP_DEBUG', '0'), FILTER_VALIDATE_BOOLEAN);
                $failed = $this->statusLine(! $debugOn, 'APP_DEBUG is off') || $failed;

                $url = trim((string) (Env::get('APP_URL') ?? ''));
                $failed = $this->statusLine($url !== '', 'APP_URL is non-empty') || $failed;
                $localUrls = ['http://localhost', 'https://localhost', 'http://127.0.0.1', 'https://127.0.0.1'];
                $normalized = strtolower(rtrim($url, '/'));
                $isLocal = in_array($normalized, $localUrls, true);
                $failed = $this->statusLine(! $isLocal, 'APP_URL is not localhost (use your public URL)') || $failed;

                $driver = strtolower((string) (Env::get('DB_DRIVER', 'sqlite')));
                if ($driver !== 'sqlite') {
                    $db = Env::get('DB_DATABASE');
                    $failed = $this->statusLine(
                        $db !== null && $db !== '',
                        'DB_DATABASE is set (driver ' . $driver . ')',
                    ) || $failed;
                }

                $appKey = trim((string) (Env::get('APP_KEY') ?? ''));
                $failed = $this->statusLine($appKey !== '', 'APP_KEY is non-empty (Crypt / signed tokens)') || $failed;
            }
        } else {
            $this->statusLine($envOk, '.env readable (optional locally; use doctor --production before deploy)', warnIfFail: false);
            if (! $envOk) {
                fwrite(STDERR, ' ' . Term::style('2', '     Tip: copy .env.example to .env') . "\n");
            }
        }

        fwrite(STDERR, "\n " . Term::style('2', 'Crypto (Password vs Crypt)') . "\n\n");
        foreach (SecurityHelp::namespaceGuide() as $line) {
            fwrite(STDERR, ' ' . Term::style('2', '· ') . $line . "\n");
        }

        if ($production) {
            fwrite(STDERR, "\n " . Term::style('2', 'Build / dependencies') . "\n\n");
            $failed = $this->statusLine(is_file($base . '/vendor/autoload.php'), 'vendor/autoload.php (run composer install)') || $failed;
            $css = $base . '/public/css/app.css';
            $cssOk = is_file($css) && filesize($css) > 128;
            $failed = $this->statusLine($cssOk, 'public/css/app.css present (npm run build:css)') || $failed;
        }

        fwrite(STDERR, "\n");
        if ($failed) {
            fwrite(STDERR, Term::style('1;31', 'Some checks failed.') . "\n\n");

            return 1;
        }

        fwrite(STDERR, Term::style('1;32', 'All required checks passed.') . "\n\n");

        return 0;
    }

    private function statusLine(bool $ok, string $message, bool $warnIfFail = true): bool
    {
        $mark = $ok ? Term::style('1;32', '✓') : ($warnIfFail ? Term::style('1;31', '✗') : Term::style('2', '·'));
        fwrite(STDERR, " {$mark}  {$message}\n");

        return ! $ok && $warnIfFail;
    }

    private function checkConfiguredUploadDirectories(string $base, string $public, bool $failed): bool
    {
        $filesConfig = $base . '/config/files.php';
        if (! is_file($filesConfig)) {
            return $failed;
        }

        $config = require $filesConfig;
        if (! is_array($config)) {
            return $failed;
        }

        $entries = FilesConfigUploadRoots::collect($config);
        if ($entries === []) {
            return $failed;
        }

        fwrite(STDERR, "\n " . Term::style('2', 'Upload directories (config/files.php)') . "\n\n");

        foreach ($entries as $entry) {
            $full = FilesConfigUploadRoots::absolutePath($base, $entry['relative']);
            $label = 'files.' . $entry['profile'] . ': ' . $entry['relative'];

            $failed = $this->statusLine(is_dir($full), "{$label} — directory exists") || $failed;
            if (! is_dir($full)) {
                continue;
            }

            $failed = $this->statusLine(PathHelp::isBelowBase($public, $full), "{$label} — under public/") || $failed;
            $failed = $this->statusLine(is_writable($full), "{$label} — writable") || $failed;

            $probe = $full . '/.doctor-write-test';
            $writeOk = @file_put_contents($probe, (string) time()) !== false;
            if ($writeOk) {
                @unlink($probe);
            }
            $failed = $this->statusLine($writeOk, "{$label} — can create/delete test file") || $failed;
        }

        return $failed;
    }
}
