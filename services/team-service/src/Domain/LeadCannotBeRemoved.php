<?php

declare(strict_types=1);

namespace App\Domain;

use Studio\Core\Http\ProblemException;

final class LeadCannotBeRemoved extends ProblemException
{
    public function __construct(int $teamId, int $developerId)
    {
        parent::__construct(
            "Developer {$developerId} is the lead of team {$teamId}. Change the lead first.",
            409
        );
    }
}