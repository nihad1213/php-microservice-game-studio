<?php

declare(strict_types=1);

use DI\Bridge\Slim\Bridge;
use DI\ContainerBuilder;
use Studio\Core\Http\{CorrelationIdMiddleware, JsonErrorHandler};

require __DIR__ . '/../vendor/autoload.php';

$builder = new ContainerBuilder();

$builder->addDefinitions(__DIR__ . '/../config/container.php');

if (getenv('APP_ENV') === 'production') {
    $builder->enableCompilation(__DIR__ . '/../storage/cache');
}

$container = $builder->build();

$app = Bridge::create($container);
$app->add(CorrelationIdMiddleware::class);
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

$errors = $app->addErrorMiddleware(getenv('APP_ENV') !== 'production', true, true);
$errors->setDefaultErrorHandler($container->get(JsonErrorHandler::class));

(require __DIR__ . '/../config/routes.php')($app);
$app->run();