<?php

declare(strict_types=1);

namespace App\Support;

final class Clock
{
    public static function nowIso(): string
    {
        return gmdate('Y-m-d\TH:i:s\Z');
    }
}
