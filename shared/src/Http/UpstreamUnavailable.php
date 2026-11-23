<?php

declare(strict_types=1);

namespace Studio\Core\Http;

use Throwable;

final class UpstreamUnavailable extends ProblemException
{
    public function __construct(string $service, ?Throwable $previous = null)
    {
        parent::__construct("{$service} is not available right now.", 503, [], $previous);
    }
}