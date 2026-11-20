<?php

use Slim\App;
use Slim\Routing\RouteCollectorProxy as Group;
use App\Http\{DeveloperController, HealthController};

return function (App $app): void {
    // $app->get('/health', HealthController::class);
    // $app->group('/api/v1/developers', function (Group $g) {
    //     $g->get('', [DeveloperController::class, 'list']);
    //     $g->post('', [DeveloperController::class, 'create']);
    //     $g->get('/{id:[0-9]+}', [DeveloperController::class, 'get']);
    //     $g->put('/{id:[0-9]+}', [DeveloperController::class, 'update']);
    //     $g->delete('/{id:[0-9]+}', [DeveloperController::class, 'delete']);
    // });
};