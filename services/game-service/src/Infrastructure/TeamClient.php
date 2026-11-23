<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\TeamDirectory;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Studio\Core\Http\UpstreamUnavailable;

final class TeamClient implements TeamDirectory
{
    public function __construct(private ClientInterface $http, private string $baseUrl) {}

    public function exists(int $teamId, ?string $correlationId = null): bool
    {
        try {
            $this->http->request('GET', "{$this->baseUrl}/api/v1/teams/{$teamId}", [
                'timeout'         => 2.0,
                'connect_timeout' => 1.0,
                'headers'         => $correlationId ? ['X-Correlation-Id' => $correlationId] : [],
            ]);

            return true;
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 404) {
                return false;
            }
            throw new UpstreamUnavailable('team-service', $e);
        } catch (GuzzleException $e) {
            throw new UpstreamUnavailable('team-service', $e);
        }
    }
}