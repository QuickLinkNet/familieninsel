<?php

declare(strict_types=1);

namespace App\Support;

final class Logger
{
    public static function security(string $logDirectory, string $message): void
    {
        if (!is_dir($logDirectory)) {
            mkdir($logDirectory, 0775, true);
        }

        $line = sprintf('[%s] %s%s', gmdate('c'), $message, PHP_EOL);
        file_put_contents($logDirectory . '/security.log', $line, FILE_APPEND);
    }
}
