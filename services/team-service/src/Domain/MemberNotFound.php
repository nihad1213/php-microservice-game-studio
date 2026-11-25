<?php

declare(strict_types=1);

namespace App\Domain;

use Studio\Core\Http\ProblemException;

final class MemberNotFound extends ProblemException
{
    public function __construct(int $teamId, int $developerId)
    {
        parent::__construct("Developer {$developerId} is not a member of team {$teamId}.", 404);
    }
}