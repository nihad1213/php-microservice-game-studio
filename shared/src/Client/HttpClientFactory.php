<?php

declare(strict_types=1);

namespace Studio\Core\Client;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;

final class HttpClientFactory
{
    public static function create(array $options = [], ?string $correlationId = null): ClientInterface {
        $defaultHeaders = [
            'Accept' => 'application/json',
        ];

        $headers = array_merge(
            $defaultHeaders,
            $options['headers'] ?? []
        );

        if ($correlationId !== null && $correlationId !== '') {
            $headers['X-Correlation-Id'] = $correlationId;
        }

        $config = array_replace(
            [
                'timeout' => 5.0,
                'connect_timeout' => 2.0,
            ],
            $options
        );

        $config['headers'] = $headers;

        return new Client($config);
    }
}
