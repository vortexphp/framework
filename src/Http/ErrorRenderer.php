<?php

declare(strict_types=1);

namespace Vortex\Http;

use Vortex\Auth\AuthorizationException;
use Vortex\Config\Repository;
use Vortex\Support\Log;
use Vortex\View\View;
use Throwable;

final class ErrorRenderer
{
    public function notFound(): Response
    {
        if (Request::wantsJson()) {
            return Response::notFound(\trans('errors.json.not_found'));
        }

        try {
            $html = View::render('errors.404', [
                'title' => \trans('errors.404.title'),
            ]);
        } catch (Throwable) {
            $html = $this->fallbackHtml(
                404,
                \trans('errors.404.title'),
                \trans('errors.404.body'),
            );
        }

        return Response::html($html, 404);
    }

    public function exception(Throwable $e): Response
    {
        if ($e instanceof AuthorizationException) {
            $message = $e->getMessage() !== '' ? $e->getMessage() : 'Forbidden';

            return Response::error(403, $message, ['error' => 'forbidden']);
        }

        Log::exception($e);

        $debug = (bool) Repository::get('app.debug', false);

        if (Request::wantsJson()) {
            $message = $debug ? $e->getMessage() : \trans('errors.json.server_error');
            $extra = [];
            if ($debug) {
                $extra['exception'] = $e::class;
                $extra['file'] = $e->getFile();
                $extra['line'] = $e->getLine();
                $extra['trace'] = explode("\n", $e->getTraceAsString());
            }

            return Response::apiError(500, 'server_error', $message, $extra);
        }

        try {
            $html = View::render('errors.500', [
                'title' => \trans('errors.500.title'),
                'message' => $debug ? $e->getMessage() : \trans('errors.500.message'),
                'trace' => $debug ? $e->getTraceAsString() : null,
            ]);
        } catch (Throwable) {
            $html = $this->fallbackHtml(
                500,
                \trans('errors.500.title'),
                $debug ? $e->getMessage() : \trans('errors.500.message'),
                $debug ? $e->getTraceAsString() : null,
            );
        }

        return Response::html($html, 500);
    }

    private function fallbackHtml(int $code, string $title, string $message, ?string $trace = null): string
    {
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        $traceBlock = '';
        if ($trace !== null && $trace !== '') {
            $safeTrace = htmlspecialchars($trace, ENT_QUOTES, 'UTF-8');
            $traceBlock = '<pre style="overflow:auto;font-size:12px;margin-top:1rem;padding:1rem;background:#111827;color:#e5e7eb;border-radius:8px">' . $safeTrace . '</pre>';
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$safeTitle}</title>
<link rel="stylesheet" href="/css/app.css">
</head>
<body class="min-h-screen bg-zinc-50 p-8 text-zinc-900 dark:bg-zinc-950 dark:text-zinc-100">
  <h1 class="text-2xl font-semibold">{$safeTitle}</h1>
  <p class="mt-2 text-zinc-600 dark:text-zinc-400">{$safeMessage}</p>
  {$traceBlock}
</body>
</html>
HTML;
    }
}
