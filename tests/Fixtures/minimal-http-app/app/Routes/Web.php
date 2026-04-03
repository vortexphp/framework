<?php

declare(strict_types=1);

use Vortex\Http\Response;
use Vortex\Routing\Route;

Route::get('/t', static fn (): Response => Response::make('ok'));
