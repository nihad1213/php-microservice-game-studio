<?php

declare(strict_types=1);

namespace App\Application;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;

final class DateParser
{
    private const PATTERN = '/^\d{4}-\d{2}-\d{2}([T ]\d{2}:\d{2}(:\d{2})?)?(Z|[+-]\d{2}:\d{2})?$/';

    /** Accepts 2026-11-20, 2026-11-20T14:30:00Z or 2026-11-20T14:30:00+02:00. Returns UTC, or null if invalid. */
    public static function parse(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || !preg_match(self::PATTERN, trim($value))) {
            return null;
        }

        $utc = new DateTimeZone('UTC');

        try {
            $date = new DateTimeImmutable(trim($value), $utc);
        } catch (Throwable) {
            return null;
        }

        $problems = DateTimeImmutable::getLastErrors();     // catches overflow dates such as 2026-02-30
        if ($problems !== false && ($problems['warning_count'] > 0 || $problems['error_count'] > 0)) {
            return null;
        }

        return $date->setTimezone($utc);
    }

    public static function isDateOnly(string $value): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($value));
    }
}