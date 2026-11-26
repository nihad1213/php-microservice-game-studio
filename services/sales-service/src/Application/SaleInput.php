<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Currency;
use App\Domain\Platform;
use App\Domain\Region;
use DateTimeImmutable;
use Respect\Validation\Validator as v;
use Studio\Core\Http\ValidationFailed;

final readonly class SaleInput
{
    private function __construct(
        public int $gameId,
        public int $units,
        public int $unitPriceCents,
        public Currency $currency,
        public Region $region,
        public Platform $platform,
        public DateTimeImmutable $soldAt,
    ) {}

    public static function fromArray(array $data, DateTimeImmutable $now): self
    {
        $data['currency'] = $data['currency'] ?? 'USD';

        $rules = [
            'game_id'          => v::intType()->min(1, true),
            'units'            => v::intType()->between(1, 100000, true),
            'unit_price_cents' => v::intType()->between(0, 1000000, true),
            'currency'         => v::stringType()->in(array_column(Currency::cases(), 'value'), true),
            'region'           => v::stringType()->in(array_column(Region::cases(), 'value'), true),
            'platform'         => v::stringType()->in(array_column(Platform::cases(), 'value'), true),
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

        $soldAt = $now;                                      // sold_at is optional: default is "now"
        if (isset($data['sold_at'])) {
            $soldAt = DateParser::parse($data['sold_at']);
            if ($soldAt === null || $soldAt > $now) {
                $errors['sold_at'] = 'must be a valid date and not in the future';
            }
        }

        if ($errors !== []) {
            throw new ValidationFailed($errors);
        }

        return new self(
            $data['game_id'],
            $data['units'],
            $data['unit_price_cents'],
            Currency::from(trim($data['currency'])),
            Region::from(trim($data['region'])),
            Platform::from(trim($data['platform'])),
            $soldAt,
        );
    }
}