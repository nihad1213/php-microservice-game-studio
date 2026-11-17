<?php

declare(strict_types=1);

namespace Studio\Core\Http;

use Psr\Http\Message\ResponseInterface;

final readonly class JsonResponder
{
    public function ok(ResponseInterface $response, array $data = [], int $status = 200): ResponseInterface
    {
        return $this->json($response, $data, $status);
    }

    public function created(ResponseInterface $response, array $data = []): ResponseInterface
    {
        return $this->json($response, $data, 201);
    }

    public function noContent(ResponseInterface $response): ResponseInterface {
        return $response->withStatus(204);
    }

    public function paginated(ResponseInterface $response, array $data, int $page, int $perPage, int $total): ResponseInterface {
        return $this->json($response, [
            'data' => $data,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int) ceil($total / max($perPage, 1)),
            ],
        ], 200);
    }


    private function json(ResponseInterface $response, array $data, int $status): ResponseInterface
    {
        $payload = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $response->getBody()->write($payload);

        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf8')
            ->withStatus($status);
    }
}