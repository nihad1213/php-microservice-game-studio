<?php

declare(strict_types=1);

namespace App\Application;

use Respect\Validation\Validator as v;
use Studio\Core\Http\ValidationFailed;

final readonly class TeamInput
{
    private function __construct(
        public string $name,
        public ?string $description,
        public ?int $leadDeveloperId,
    ) {}

    public static function fromArray(array $data): self
    {
        $rules = [
            'name'              => v::stringType()->length(1, 100),
            'description'       => v::nullable(v::stringType()->length(0, 1000)),
            'lead_developer_id' => v::nullable(v::intType()->min(1, true)),
        ];

        $errors = [];
        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;
            if (is_string($value)) {
                $value = trim($value);
            }
            if (!$rule->validate($value)) {
                $errors[$field] = 'missing or invalid';
            }
        }
        if ($errors !== []) {
            throw new ValidationFailed($errors);
        }

        $description = isset($data['description']) ? trim($data['description']) : null;

        return new self(
            trim($data['name']),
            $description === '' ? null : $description,
            $data['lead_developer_id'] ?? null,
        );
    }
}