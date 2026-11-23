<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Platform;
use Respect\Validation\Validator as v;
use Studio\Core\Http\ValidationFailed;

final readonly class GameInput
{
    private function __construct(
        public string $title,
        public string $genre,
        public Platform $platform,
        public int $priceCents,
        public int $teamId,
    ) {}

    public static function fromArray(array $data): self
    {
        $rules = [
            'title'       => v::stringType()->length(1, 200),
            'genre'       => v::stringType()->length(1, 50),
            'platform'    => v::stringType()->in(array_column(Platform::cases(), 'value'), true),
            'price_cents' => v::intType()->between(0, 1000000),
            'team_id'     => v::intType()->min(1),
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
            trim($data['title']),
            strtolower(trim($data['genre'])),
            Platform::from(trim($data['platform'])),
            $data['price_cents'],
            $data['team_id'],
        );
    }
}