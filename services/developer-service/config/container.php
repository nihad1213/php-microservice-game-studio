<?php
use function DI\autowire;
use Psr\Log\LoggerInterface;
use Studio\Core\Support\LoggerFactory;

return [
    LoggerInterface::class => fn () => LoggerFactory::create('developer-service'),
    Psr\Http\Message\ResponseFactoryInterface::class => autowire(Slim\Psr7\Factory\ResponseFactory::class),

    PDO::class => function (): PDO {
        $dsn = sprintf('mysql:host=%s;port=3306;dbname=%s;charset=utf8mb4',
                getenv('DB_HOST'), getenv('DB_NAME'));
    
        return new PDO($dsn, getenv('DB_USER'), getenv('DB_PASS'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    },
    
    // App\Domain\DeveloperRepository::class => autowire(App\Infrastructure\PdoDeveloperRepository::class),
    // Psr\Http\Message\ResponseFactoryInterface::class => autowire(Slim\Psr7\Factory\ResponseFactory::class),
];