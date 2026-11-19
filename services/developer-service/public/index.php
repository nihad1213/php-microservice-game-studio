<?php

declare(strict_types=1);

use DI\Bridge\Slim\Bridge;
use DI\ContainerBuilder;
use Studio\Core\Http\{CorrelationIdMiddleware, JsonErrorHandler};

require __DIR__ . '/../vendor/autoload.php';

