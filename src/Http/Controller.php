<?php

declare(strict_types=1);

namespace Vortex\Http;

use Vortex\View\View;

/**
 * Optional base for action classes. Lets handlers return {@see Response} from small helpers instead of only static {@see Response} calls.
 */
abstract class Controller
{
    protected function json(mixed $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    protected function html(string $content, int $status = 200): Response
    {
        return Response::html($content, $status);
    }

    protected function redirect(string $to, int $status = 302): Response
    {
        return Response::redirect($to, $status);
    }

    protected function apiOk(mixed $data, int $status = 200): Response
    {
        return Response::apiOk($data, $status);
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function view(string $name, array $data = [], int $status = 200): Response
    {
        return View::html($name, $data, $status);
    }
}
