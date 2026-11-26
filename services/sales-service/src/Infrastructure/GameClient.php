<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\GameDirectory;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Studio\Core\Http\UpstreamUnavailable;

final class GameClient implements GameDirectory
{
    public function __construct(private ClientInterface $http, private string $baseUrl) {}

    public function status(int $gameId, ?string $correlationId = null): ?string
    {
        try {
            $res = $this->http->request('GET', "{$this->baseUrl}/api/v1/games/{$gameId}", [
                'timeout'         => 2.0,
                'connect_timeout' => 1.0,
                'headers'         => $correlationId ? ['X-Correlation-Id' => $correlationId] : [],
            ]);

            $game = json_decode((string) $res->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 404) {
                return null;
            }
            throw new UpstreamUnavailable('game-service', $e);
        } catch (GuzzleException | JsonException $e) {
            throw new UpstreamUnavailable('game-service', $e);
        }

        if (!is_string($game['status'] ?? null)) {
            throw new UpstreamUnavailable('game-service');
        }

        return $game['status'];
    }
}