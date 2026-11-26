<?php

declare(strict_types=1);

use App\Http\HealthController;
use App\Http\SalesController;
use Slim\App;
use Slim\Routing\RouteCollectorProxy as Group;

return function (App $app): void {
    $app->get('/health', HealthController::class);

    $app->group('/api/v1/sales', function (Group $g) {
        $g->get('', [SalesController::class, 'list']);
        $g->post('', [SalesController::class, 'create']);
        $g->post('/batch', [SalesController::class, 'batch']);

        $g->get('/reports/by-game', [SalesController::class, 'reportByGame']);
        $g->get('/reports/by-region', [SalesController::class, 'reportByRegion']);
        $g->get('/reports/monthly', [SalesController::class, 'reportMonthly']);

        $g->get('/{id:[0-9]+}', [SalesController::class, 'get']);
    });
};