<?php

declare(strict_types=1);

namespace App\Security\Pii;

use InvalidArgumentException;

/**
 * Plaintext data keys, held only in memory.
 *
 * - encryption keys: id => 32 raw bytes. Several may exist so data written
 *   with an older key stays readable after rotation; `currentKeyId` encrypts.
 * - index key: 32 raw bytes for the HMAC blind index (never used to encrypt).
 */
final readonly class KeyMaterial
{
    /**
     * @param array<string, string> $encryptionKeys
     */
    public function __construct(
        public array $encryptionKeys,
        public string $currentKeyId,
        public string $indexKey,
    ) {
        if (! isset($encryptionKeys[$currentKeyId])) {
            throw new InvalidArgumentException("The current PII key \"{$currentKeyId}\" is not in the key ring.");
        }
        foreach ($encryptionKeys as $id => $key) {
            if (preg_match('/^[a-z0-9]{1,16}$/', (string) $id) !== 1) {
                throw new InvalidArgumentException('PII key ids must be 1-16 lowercase letters or digits.');
            }
            if (strlen($key) !== 32) {
                throw new InvalidArgumentException("PII key \"{$id}\" must be exactly 32 bytes.");
            }
        }
        if (strlen($indexKey) !== 32) {
            throw new InvalidArgumentException('The PII index key must be exactly 32 bytes.');
        }
        if (in_array($indexKey, $encryptionKeys, true)) {
            throw new InvalidArgumentException('The PII index key must differ from every encryption key.');
        }
    }
}
