<?php

declare(strict_types=1);

use App\Domain\DeveloperDirectory;
use App\Domain\TeamRepository;
use App\Infrastructure\DeveloperClient;
use App\Infrastructure\PdoTeamRepository;
use GuzzleHttp\ClientInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Studio\Core\Client\HttpClientFactory;
use Studio\Core\Event\EventBus;
use Studio\Core\Event\LogEventBus;
use Studio\Core\Support\LoggerFactory;

use function DI\autowire;

return [
    LoggerInterface::class          => fn () => LoggerFactory::create('team-service'),
    ResponseFactoryInterface::class => autowire(ResponseFactory::class),
    EventBus::class                 => autowire(LogEventBus::class),

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

    TeamRepository::class => autowire(PdoTeamRepository::class),

    ClientInterface::class => fn () => HttpClientFactory::create(),

    DeveloperDirectory::class => fn (ContainerInterface $c) => new DeveloperClient(
        $c->get(ClientInterface::class),
        rtrim(getenv('DEVELOPER_SERVICE_URL') ?: 'http://localhost:8002', '/'),
    ),
];