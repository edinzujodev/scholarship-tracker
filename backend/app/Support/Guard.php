<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * Domain invariants for entities. Entities reject invalid state no matter
 * where it comes from (API, seeder, future imports), not only at the HTTP
 * boundary. Violations are programming or data errors, so they throw.
 */
final class Guard
{
    /** Trimmed, non-empty text of at most $max characters. */
    public static function requiredText(string $value, string $label, int $max): string
    {
        $value = trim($value);
        if ($value === '') {
            throw new InvalidArgumentException("{$label} is required.");
        }

        return self::maxLength($value, $label, $max);
    }

    /** Trimmed text of at most $max characters, or null when blank. */
    public static function optionalText(?string $value, string $label, int $max): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === null || $value === '' ? null : self::maxLength($value, $label, $max);
    }

    public static function email(string $value): string
    {
        $value = mb_strtolower(trim($value));
        if (mb_strlen($value) > 255 || filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Email must be a valid address of at most 255 characters.');
        }

        return $value;
    }

    public static function intBetween(int $value, string $label, int $min, int $max): int
    {
        if ($value < $min || $value > $max) {
            throw new InvalidArgumentException("{$label} must be between {$min} and {$max}.");
        }

        return $value;
    }

    /**
     * A list (not a map) of 0..$maxItems non-empty strings, each at most
     * $maxLength characters.
     *
     * @param array<mixed> $items
     * @return list<string>
     */
    public static function textList(array $items, string $label, int $maxItems, int $maxLength): array
    {
        if (! array_is_list($items) || count($items) > $maxItems) {
            throw new InvalidArgumentException("{$label} must be a list of at most {$maxItems} items.");
        }

        return array_map(static function (mixed $item) use ($label, $maxLength): string {
            if (! is_string($item)) {
                throw new InvalidArgumentException("{$label} may only contain text.");
            }

            return self::requiredText($item, "{$label} item", $maxLength);
        }, $items);
    }

    private static function maxLength(string $value, string $label, int $max): string
    {
        if (mb_strlen($value) > $max) {
            throw new InvalidArgumentException("{$label} must be at most {$max} characters.");
        }

        return $value;
    }
}
