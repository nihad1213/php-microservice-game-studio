<?php

declare(strict_types=1);

use App\Http\GameController;
use App\Http\HealthController;
use Slim\App;
use Slim\Routing\RouteCollectorProxy as Group;

return function (App $app): void {
    $app->get('/health', HealthController::class);

    $app->group('/api/v1/games', function (Group $g) {
        $g->get('', [GameController::class, 'list']);
        $g->post('', [GameController::class, 'create']);
        $g->get('/{id:[0-9]+}', [GameController::class, 'get']);
        $g->put('/{id:[0-9]+}', [GameController::class, 'update']);
        $g->delete('/{id:[0-9]+}', [GameController::class, 'delete']);
        $g->post('/{id:[0-9]+}/release', [GameController::class, 'release']);
        $g->post('/{id:[0-9]+}/retire', [GameController::class, 'retire']);
    });
};