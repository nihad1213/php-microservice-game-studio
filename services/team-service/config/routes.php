<?php

declare(strict_types=1);

use App\Http\HealthController;
use App\Http\TeamController;
use Slim\App;
use Slim\Routing\RouteCollectorProxy as Group;

return function (App $app): void {
    $app->get('/health', HealthController::class);

    $app->group('/api/v1/teams', function (Group $g) {
        $g->get('', [TeamController::class, 'list']);
        $g->post('', [TeamController::class, 'create']);
        $g->get('/{id:[0-9]+}', [TeamController::class, 'get']);
        $g->put('/{id:[0-9]+}', [TeamController::class, 'update']);
        $g->delete('/{id:[0-9]+}', [TeamController::class, 'delete']);

        $g->get('/{id:[0-9]+}/members', [TeamController::class, 'members']);
        $g->post('/{id:[0-9]+}/members', [TeamController::class, 'addMember']);
        $g->delete('/{id:[0-9]+}/members/{developerId:[0-9]+}', [TeamController::class, 'removeMember']);
    });
};