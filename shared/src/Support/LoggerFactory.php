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
    public static function create(string $service): LoggerInterface
    {
        $handler = new StreamHandler('php://stdout', Level::Info);
        $handler->setFormatter(new JsonFormatter());

        return new Logger($service, [$handler]);
    }
}