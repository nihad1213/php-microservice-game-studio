<?php

declare(strict_types=1);

use function DI\autowire;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Studio\Core\Support\LoggerFactory;

return [
    LoggerInterface::class => fn () => LoggerFactory::create('sales-service'),

    ResponseFactoryInterface::class => autowire(ResponseFactory::class),

    PDO::class => function (): PDO {
        $path = getenv('SQLITE_PATH') ?: __DIR__ . '/../storage/sales.sqlite';

        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $pdo->exec('PRAGMA journal_mode = WAL;');
        $pdo->exec('PRAGMA synchronous = NORMAL;');
        $pdo->exec('PRAGMA foreign_keys = ON;');
        $pdo->exec('PRAGMA busy_timeout = 5000;');

        return $pdo;
    },

    // App\Domain\SalesRepository::class => autowire(App\Infrastructure\SqliteSalesRepository::class),
];