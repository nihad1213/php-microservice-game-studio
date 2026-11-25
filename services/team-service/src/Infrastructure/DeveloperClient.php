<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\DeveloperDirectory;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\Utils;
use Studio\Core\Http\UpstreamUnavailable;
use Throwable;

final class DeveloperClient implements DeveloperDirectory
{
    public function __construct(private ClientInterface $http, private string $baseUrl) {}

    public function exists(int $developerId, ?string $correlationId = null): bool
    {
        try {
            $this->http->request('GET', $this->url($developerId), $this->options($correlationId));

            return true;
        } catch (ClientException $e) { 
            if ($e->getResponse()->getStatusCode() === 404) {
                return false;
            }
            throw new UpstreamUnavailable('developer-service', $e);
        } catch (GuzzleException $e) {
            throw new UpstreamUnavailable('developer-service', $e);
        }
    }

    public function findMany(array $developerIds, ?string $correlationId = null): array
    {
        $promises = [];
        foreach (array_unique($developerIds) as $id) {
            $promises[$id] = $this->http->requestAsync('GET', $this->url($id), $this->options($correlationId));
        }

        $result = [];
        foreach (Utils::settle($promises)->wait() as $id => $outcome) {
            if ($outcome['state'] === PromiseInterface::FULFILLED) {
                $result[$id] = json_decode((string) $outcome['value']->getBody(), true, 512, JSON_THROW_ON_ERROR);
                continue;
            }

            $reason = $outcome['reason'];
            if ($reason instanceof ClientException && $reason->getResponse()->getStatusCode() === 404) {
                $result[$id] = null;
                continue;
            }

            throw new UpstreamUnavailable('developer-service', $reason instanceof Throwable ? $reason : null);
        }

        return $result;
    }

    private function url(int $id): string
    {
        return "{$this->baseUrl}/api/v1/developers/{$id}";
    }

    private function options(?string $correlationId): array
    {
        return [
            'timeout'         => 2.0,
            'connect_timeout' => 1.0,
            'headers'         => $correlationId ? ['X-Correlation-Id' => $correlationId] : [],
        ];
    }
}