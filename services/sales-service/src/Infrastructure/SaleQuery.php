<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\SaleFilter;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOStatement;

final class SaleQuery
{
    /** @return array{0: string, 1: array<string, int|string>} */
    public static function where(SaleFilter $f): array
    {
        $parts  = [];
        $params = [];

        if ($f->gameId !== null)   { $parts[] = 'game_id = :game_id';   $params['game_id']  = $f->gameId; }
        if ($f->from !== null)     { $parts[] = 'sold_at >= :from';     $params['from']     = self::format($f->from); }
        if ($f->to !== null)       { $parts[] = 'sold_at <= :to';       $params['to']       = self::format($f->to); }
        if ($f->region !== null)   { $parts[] = 'region = :region';     $params['region']   = $f->region->value; }
        if ($f->platform !== null) { $parts[] = 'platform = :platform'; $params['platform'] = $f->platform->value; }

        return [$parts ? ' WHERE ' . implode(' AND ', $parts) : '', $params];
    }

    public static function bind(PDOStatement $stmt, array $params): void
    {
        foreach ($params as $name => $value) {
            $stmt->bindValue(':' . $name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
    }

    /** SQLite has no date type: UTC text in the format Y-m-d H:i:s sorts and compares correctly. */
    public static function format(DateTimeImmutable $date): string
    {
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}