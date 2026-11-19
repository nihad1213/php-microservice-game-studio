<?php

declare(strict_types=1);

namespace Studio\Core\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Ramsey\Uuid\Uuid;

final readonly class CorrelationIdMiddleware implements MiddlewareInterface
{
    private const HEADER = 'X-Correlation-Id';

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler ): ResponseInterface {
        $correlationId = $request->getHeaderLine(self::HEADER);

        if (!$this->isValidUuid($correlationId)) {
            $correlationId = Uuid::uuid4()->toString();
        }

        $request = $request
            ->withHeader(self::HEADER, $correlationId)
            ->withAttribute('correlation_id', $correlationId);

        $response = $handler->handle($request);

        return $response->withHeader(self::HEADER, $correlationId);
    }

    private function isValidUuid(string $value): bool
    {
        return $value !== ''
            && Uuid::isValid($value);
    }
}
