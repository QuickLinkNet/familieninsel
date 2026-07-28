<?php

declare(strict_types=1);

return [
    'app' => [
        'timezone' => 'UTC',
    ],
    'cors' => [
        'allowed_origins' => [
            'http://localhost:5173',
            'https://www.red-it.org',
        ],
    ],
    'database' => [
        'path' => dirname(__DIR__) . '/storage/database/familieninsel.sqlite',
    ],
];
