<?php

declare(strict_types=1);

namespace App\Domain;

enum Region: string
{
    case NorthAmerica = 'north_america';
    case Europe       = 'europe';
    case Asia         = 'asia';
    case SouthAmerica = 'south_america';
    case Oceania      = 'oceania';
    case Africa       = 'africa';
}