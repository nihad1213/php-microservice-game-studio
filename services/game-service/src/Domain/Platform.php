<?php

declare(strict_types=1);

namespace App\Domain;

enum Platform: string
{
    case Pc = 'pc';
    case Playstation = 'playstation';
    case Xbox = 'xbox';
    case Switch = 'switch';
    case Mobile = 'mobile';
}