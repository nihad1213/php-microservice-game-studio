<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\GameDirectory;
use Predis\ClientInterface as Redis;
use Psr\Log\LoggerInterface;
use Throwable;

final class CachedGameDirectory implements GameDirectory
{
    public function __construct(
        private GameDirectory $inner,
        private Redis $redis,
        private LoggerInterface $logger,
        private int $ttlSeconds = 60,
    ) {}

    public function status(int $gameId, ?string $correlationId = null): ?string
    {
        $key = "sales:game:{$gameId}:status";

        try {
            $cached = $this->redis->get($key);
            if (is_string($cached) && $cached !== '') {
                return $cached;
            }
        } catch (Throwable $e) {
            $this->logger->warning('Cache read failed', ['key' => $key, 'error' => $e->getMessage()]);
        }

        $status = $this->inner->status($gameId, $correlationId);

        if ($status !== null) {
            try {
                $this->redis->setex($key, $this->ttlSeconds, $status);
            } catch (Throwable $e) {
                $this->logger->warning('Cache write failed', ['key' => $key, 'error' => $e->getMessage()]);
            }
        }

        return $status;
    }
}