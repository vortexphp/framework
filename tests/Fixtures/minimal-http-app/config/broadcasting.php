<?php

declare(strict_types=1);

return [
    'driver' => 'sync',
    'redis' => [
        'host' => '127.0.0.1',
        'port' => 6379,
        'database' => 0,
        'prefix' => 'vortex:broadcast:',
    ],
];
