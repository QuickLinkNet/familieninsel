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
        // Inaktivitaets-Timeout der gesamten Sitzung (Sekunden). Bewusst lang:
        // Kinder-Tablets melden sich per QR-Code einmalig an und sollen danach
        // dauerhaft angemeldet bleiben, nicht nach wenigen Stunden Pause.
        'idle_timeout_seconds' => 2592000,
        // Cookie-Laufzeit fuer per QR-Code angemeldete Kinder-Geraete (Sekunden).
        // Macht aus dem sonst fluechtigen PHP-Session-Cookie (lifetime=0, weg
        // beim Schliessen des Browsers) ein dauerhaftes Login pro Tablet.
        'child_session_lifetime_seconds' => 15552000,
    ],
    'security' => [
        // Fehlversuche beim Eltern-Passwort-Login, bevor kurzzeitig gesperrt wird.
        'login_max_attempts' => 5,
        'login_lockout_seconds' => 60,
    ],
];
