<?php

declare(strict_types=1);

namespace App\Security\Pii;

/**
 * Keyed, deterministic fingerprint of a value (HMAC-SHA256), stored next to
 * the encrypted value so it can be looked up and kept unique without
 * decrypting anything. Without the index key the fingerprint reveals nothing
 * and cannot be brute-forced offline the way a plain hash of an email could.
 *
 * The field name is part of the input, so the same text in two different
 * fields produces unrelated fingerprints.
 */
final class BlindIndex
{
    public function __construct(private readonly KeyMaterial $keys)
    {
    }

    public function of(string $field, string $value): string
    {
        return hash_hmac('sha256', $field."\0".$this->normalise($value), $this->keys->indexKey);
    }

    /** Lookups must match regardless of case or surrounding spaces. */
    private function normalise(string $value): string
    {
        return mb_strtolower(trim($value));
    }
}
