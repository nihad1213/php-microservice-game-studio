<?php

declare(strict_types=1);

namespace App\Application;

use DateTimeImmutable;
use Studio\Core\Http\ValidationFailed;

final readonly class BatchInput
{
    public const MAX_ITEMS = 500;

    /** @param SaleInput[] $items */
    private function __construct(public array $items) {}

    public static function fromArray(array $data, DateTimeImmutable $now): self
    {
        $items = $data['items'] ?? null;

        if (!is_array($items) || $items === [] || !array_is_list($items)) {
            throw new ValidationFailed(['items' => 'must be a non-empty list']);
        }
        if (count($items) > self::MAX_ITEMS) {
            throw new ValidationFailed(['items' => 'at most ' . self::MAX_ITEMS . ' items per request']);
        }

        $parsed = [];
        $errors = [];

        foreach ($items as $i => $item) {
            try {
                $parsed[] = SaleInput::fromArray(is_array($item) ? $item : [], $now);
            } catch (ValidationFailed $e) {
                foreach ($e->getErrors() as $field => $message) {
                    $errors["items.{$i}.{$field}"] = $message;
                }
                if (count($errors) >= 50) {                  // enough to fix the file, keep the answer small
                    break;
                }
            }
        }

        if ($errors !== []) {
            throw new ValidationFailed($errors);
        }

        return new self($parsed);
    }
}