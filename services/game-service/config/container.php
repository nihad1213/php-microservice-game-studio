<?php

declare(strict_types=1);

use App\Domain\GameRepository;
use App\Domain\TeamDirectory;
use App\Infrastructure\AllowAllTeamDirectory;
use App\Infrastructure\PdoGameRepository;
use App\Infrastructure\TeamClient;
use GuzzleHttp\ClientInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Studio\Core\Client\HttpClientFactory;
use Studio\Core\Event\EventBus;
use Studio\Core\Event\LogEventBus;
use Studio\Core\Support\Clock;
use Studio\Core\Support\LoggerFactory;
use Studio\Core\Support\SystemClock;

use function DI\autowire;

return [
    LoggerInterface::class          => fn () => LoggerFactory::create('game-service'),
    ResponseFactoryInterface::class => autowire(ResponseFactory::class),
    Clock::class                    => autowire(SystemClock::class),
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

    GameRepository::class => autowire(PdoGameRepository::class),

    ClientInterface::class => fn () => HttpClientFactory::create(),

    TeamDirectory::class => function (ContainerInterface $c): TeamDirectory {
        if (getenv('TEAM_CHECK') === 'false') {
            return new AllowAllTeamDirectory();
        }

        return new TeamClient(
            $c->get(ClientInterface::class),
            rtrim(getenv('TEAM_SERVICE_URL') ?: 'http://team-service', '/'),
        );
    },
];