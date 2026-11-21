<?php

declare(strict_types=1);

namespace App\Domain;

enum Role: string
{
    case Programmer = 'programmer';
    case Designer = 'designer';
    case Artist = 'artist';
    case Producer = 'producer';
    case Qa = 'qa';
}