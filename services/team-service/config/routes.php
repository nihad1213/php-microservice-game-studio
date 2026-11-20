<?php

declare(strict_types=1);

use App\Http\HealthController;
use Slim\App;

return function (App $app): void {
    $app->get('/health', HealthController::class);
};