<?php

declare(strict_types=1);

namespace Studio\Core\Http;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpException;
use Throwable;

final readonly class JsonErrorHandler
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(
        ServerRequestInterface $request,
        Throwable $exception,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails
    ): ResponseInterface {
        $status = $this->getStatusCode($exception);

        $safe = $exception instanceof ProblemException && $status < 500;

        $correlationId = $request->getHeaderLine('X-Correlation-Id');

        if ($logErrors) {
            $this->logger->error('HTTP request failed', [
                'status' => $status,
                'exception' => $logErrorDetails
                    ? $exception
                    : $exception::class,
                'correlation_id' => $correlationId ?: null,
            ]);
        }

        $problem = [
            'type' => 'about:blank',
            'title' => $this->getTitle($status),
            'status' => $status,
            'detail' => ($displayErrorDetails || $safe)
                ? $exception->getMessage()
                : 'An error occurred while processing the request.',
            'instance' => $request->getUri()->getPath(),
        ];

        if ($exception instanceof ProblemException && $exception->getErrors() !== []) {
            $problem['errors'] = $exception->getErrors();
        }

        $response = $this->responseFactory->createResponse($status);

        $response->getBody()->write(
            json_encode(
                $problem,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
            )
        );

        $response = $response->withHeader(
            'Content-Type',
            'application/problem+json'
        );

        if ($correlationId !== '') {
            $response = $response->withHeader(
                'X-Correlation-Id',
                $correlationId
            );
        }

        return $response;
    }

    private function getStatusCode(Throwable $exception): int
    {
        if ($exception instanceof ProblemException) {
            return $exception->getStatus();
        }

        if ($exception instanceof HttpException) {
            $status = $exception->getCode();

            if ($status >= 400 && $status <= 599) {
                return $status;
            }
        }

        return 500;
    }

    private function getTitle(int $status): string
    {
        return match ($status) {
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            406 => 'Not Acceptable',
            409 => 'Conflict',
            410 => 'Gone',
            415 => 'Unsupported Media Type',
            422 => 'Unprocessable Entity',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            501 => 'Not Implemented',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
            default => 'HTTP Error',
        };
    }
}