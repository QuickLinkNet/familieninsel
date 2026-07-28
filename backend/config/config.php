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
        'migrations_path' => dirname(__DIR__) . '/database/migrations',
    ],
    'session' => [
        'cookie_name' => 'familieninsel_session',
        // Inaktivitaets-Timeout der gesamten Sitzung (Sekunden).
        'idle_timeout_seconds' => 7200,
        // Wie lange eine Eltern-PIN-Freischaltung gilt, bevor sie erneut verlangt wird.
        'parent_unlock_seconds' => 900,
    ],
    'security' => [
        'pin_max_attempts' => 5,
        'pin_lockout_seconds' => 60,
    ],
];
