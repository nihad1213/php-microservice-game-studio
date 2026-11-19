
<?php

declare(strict_types=1);

namespace Studio\Core\Support;

use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

final class LoggerFactory
{
    public static function create(string $serviceName): LoggerInterface
    {
        $logger = new Logger($serviceName);

        $handler = new StreamHandler(
            'php://stdout',
            self::getLogLevel()
        );

        $handler->setFormatter(
            new JsonFormatter(
                JsonFormatter::BATCH_MODE_JSON,
                true
            )
        );

        $logger->pushHandler($handler);

        $logger->pushProcessor(
            static function ($record) use ($serviceName) {
                return $record->with(
                    extra: array_merge(
                        $record->extra,
                        ['service' => $serviceName]
                    )
                );
            }
        );

        return $logger;
    }

    private static function getLogLevel(): Level
    {
        $level = getenv('LOG_LEVEL') ?: 'info';

        return Level::fromName(strtoupper($level));
    }
}
