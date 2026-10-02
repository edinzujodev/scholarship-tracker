<?php

declare(strict_types=1);

namespace App\Security\Pii;

use RuntimeException;

/**
 * Authenticated encryption for single database fields (AES-256-GCM).
 *
 * Stored format:  v1.<keyId>.<base64(nonce[12] | tag[16] | ciphertext)>
 *
 *  - a fresh random nonce per value, so equal plaintexts never produce equal
 *    ciphertexts (no frequency analysis);
 *  - the GCM tag detects any tampering or a wrong key;
 *  - the key id lets old values decrypt after the current key is rotated.
 */
final class FieldCipher
{
    private const VERSION = 'v1';
    private const CIPHER = 'aes-256-gcm';
    private const NONCE_BYTES = 12;
    private const TAG_BYTES = 16;

    public function __construct(private readonly KeyMaterial $keys)
    {
    }

    public function encrypt(string $plaintext): string
    {
        $nonce = random_bytes(self::NONCE_BYTES);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $this->keys->encryptionKeys[$this->keys->currentKeyId],
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            self::VERSION,
            self::TAG_BYTES,
        );
        if ($ciphertext === false) {
            throw new RuntimeException('Encryption failed.');
        }

        return sprintf('%s.%s.%s', self::VERSION, $this->keys->currentKeyId, base64_encode($nonce.$tag.$ciphertext));
    }

    public function decrypt(string $stored): string
    {
        $parts = explode('.', $stored, 3);
        if (count($parts) !== 3 || $parts[0] !== self::VERSION) {
            throw new RuntimeException('Value is not in the encrypted field format.');
        }
        [, $keyId, $payload] = $parts;
        $key = $this->keys->encryptionKeys[$keyId] ?? throw new RuntimeException("Unknown PII key id \"{$keyId}\".");

        $raw = base64_decode($payload, true);
        if ($raw === false || strlen($raw) < self::NONCE_BYTES + self::TAG_BYTES) {
            throw new RuntimeException('Encrypted value is malformed.');
        }

        $plaintext = openssl_decrypt(
            substr($raw, self::NONCE_BYTES + self::TAG_BYTES),
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            substr($raw, 0, self::NONCE_BYTES),
            substr($raw, self::NONCE_BYTES, self::TAG_BYTES),
            self::VERSION,
        );
        if ($plaintext === false) {
            throw new RuntimeException('Decryption failed: wrong key or tampered value.');
        }

        return $plaintext;
    }

    /** The key id a stored value was written with (used when re-encrypting after rotation). */
    public static function keyIdOf(string $stored): ?string
    {
        $parts = explode('.', $stored, 3);

        return count($parts) === 3 ? $parts[1] : null;
    }
}
