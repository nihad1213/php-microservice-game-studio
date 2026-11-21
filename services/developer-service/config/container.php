<?php

declare(strict_types=1);

use App\Domain\DeveloperRepository;
use App\Infrastructure\PdoDeveloperRepository;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Studio\Core\Support\LoggerFactory;

use function DI\autowire;

return [
    LoggerInterface::class => fn () => LoggerFactory::create('developer-service'),

    ResponseFactoryInterface::class => autowire(ResponseFactory::class),

    PDO::class => function (): PDO {
        $dsn = sprintf(
            'mysql:host=%s;port=3306;dbname=%s;charset=utf8mb4',
            getenv('DB_HOST'),
            getenv('DB_NAME')
        );

        return new PDO($dsn, getenv('DB_USER'), getenv('DB_PASS'), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    },

    DeveloperRepository::class => autowire(PdoDeveloperRepository::class),
];