<?php

declare(strict_types=1);

namespace App\Application;

use Respect\Validation\Validator as v;
use Studio\Core\Http\ValidationFailed;

final readonly class MemberInput
{
    private function __construct(public int $developerId) {}

    public static function fromArray(array $data): self
    {
        if (!v::intType()->min(1)->validate($data['developer_id'] ?? null)) {
            throw new ValidationFailed(['developer_id' => 'missing or invalid']);
        }

        return new self($data['developer_id']);
    }
}