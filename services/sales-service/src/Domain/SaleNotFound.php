<?php

declare(strict_types=1);

namespace App\Domain;

use Studio\Core\Http\ProblemException;

final class SaleNotFound extends ProblemException
{
    public function __construct(int $id)
    {
        parent::__construct("Sale {$id} was not found.", 404);
    }
}