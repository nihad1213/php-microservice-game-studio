<?php

declare(strict_types=1);

namespace App\Domain;

enum GameStatus: string
{
    case InDevelopment = 'in_development';
    case Released = 'released';
    case Retired = 'retired';
}