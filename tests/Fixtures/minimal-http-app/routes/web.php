<?php

declare(strict_types=1);

use Vortex\Http\Request;
use Vortex\Http\Response;
use Vortex\Routing\Route;
use Vortex\Support\JsonHelp;

Route::get('/t', static fn (): Response => Response::make('ok'));

Route::post('/echo-json', static function (): Response {
    $payload = array_merge(Request::body(), ['method' => Request::method()]);

    return Response::make(JsonHelp::encode($payload))
        ->header('Content-Type', 'application/json');
});
