<?php

declare(strict_types=1);

use App\Domain\GameDirectory;
use App\Domain\SaleRepository;
use App\Domain\SalesReport;
use App\Infrastructure\CachedGameDirectory;
use App\Infrastructure\GameClient;
use App\Infrastructure\SqliteSalesReport;
use App\Infrastructure\SqliteSalesRepository;
use GuzzleHttp\ClientInterface;
use Predis\Client as RedisClient;
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
    LoggerInterface::class          => fn () => LoggerFactory::create('sales-service'),
    ResponseFactoryInterface::class => autowire(ResponseFactory::class),
    Clock::class                    => autowire(SystemClock::class),
    EventBus::class                 => autowire(LogEventBus::class),

    PDO::class => function (): PDO {
        $path = getenv('SQLITE_PATH') ?: __DIR__ . '/../storage/sales.sqlite';

        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $pdo->exec('PRAGMA journal_mode = WAL;');     // readers don't block the writer
        $pdo->exec('PRAGMA synchronous = NORMAL;');   // safe with WAL, much faster
        $pdo->exec('PRAGMA foreign_keys = ON;');      // off by default in SQLite
        $pdo->exec('PRAGMA busy_timeout = 5000;');    // wait up to 5 s if the DB is locked

        return $pdo;
    },

    SaleRepository::class => autowire(SqliteSalesRepository::class),
    SalesReport::class    => autowire(SqliteSalesReport::class),

    ClientInterface::class => fn () => HttpClientFactory::create(),

    RedisClient::class => fn () => new RedisClient([
        'scheme'             => 'tcp',
        'host'               => getenv('REDIS_HOST') ?: 'redis',
        'port'               => 6379,
        'timeout'            => 0.5,
        'read_write_timeout' => 0.5,
    ]),

    GameDirectory::class => function (ContainerInterface $c): GameDirectory {
        $client = new GameClient(
            $c->get(ClientInterface::class),
            rtrim(getenv('GAME_SERVICE_URL') ?: 'http://localhost:8003', '/'),
        );

        return new CachedGameDirectory($client, $c->get(RedisClient::class), $c->get(LoggerInterface::class));
    },
];