<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Role;
use DateTimeImmutable;
use Respect\Validation\Validator as v;
use Studio\Core\Http\ValidationFailed;

final readonly class DeveloperInput
{
    private function __construct(
        public string $firstName,
        public string $lastName,
        public string $email,
        public Role $role,
        public DateTimeImmutable $hiredAt,
    ) {}

    public static function fromArray(array $data): self
    {
        $rules = [
            'first_name' => v::stringType()->length(1, 100),
            'last_name'  => v::stringType()->length(1, 100),
            'email'      => v::stringType()->email()->length(1, 190),
            'role'       => v::stringType()->in(array_column(Role::cases(), 'value'), true),
            'hired_at'   => v::stringType()->date('Y-m-d'),
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

        return new self(
            trim($data['first_name']),
            trim($data['last_name']),
            strtolower(trim($data['email'])),
            Role::from(trim($data['role'])),
            new DateTimeImmutable(trim($data['hired_at'])),
        );
    }
}