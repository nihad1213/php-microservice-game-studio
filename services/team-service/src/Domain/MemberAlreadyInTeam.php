<?php

declare(strict_types=1);

namespace App\Domain;

use Studio\Core\Http\ProblemException;

final class MemberAlreadyInTeam extends ProblemException
{
    public function __construct(int $teamId, int $developerId)
    {
        parent::__construct("Developer {$developerId} is already a member of team {$teamId}.", 409);
    }
}