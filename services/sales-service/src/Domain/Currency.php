<?php

declare(strict_types=1);

namespace App\Domain;

enum Currency: string
{
    case Usd = 'USD';
    case Eur = 'EUR';
    case Gbp = 'GBP';
}