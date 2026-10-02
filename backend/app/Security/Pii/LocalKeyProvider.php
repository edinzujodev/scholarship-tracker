<?php

declare(strict_types=1);

namespace App\Security\Pii;

use RuntimeException;

/**
 * Development and test keys. Refused in production: real deployments must
 * use Cloud KMS, so plaintext keys never live in a production config.
 *
 * Without explicit keys, both keys are derived from APP_KEY with HKDF, using
 * different labels, so encryption and indexing never share a key.
 */
final class LocalKeyProvider implements KeyProvider
{
    /**
     * @param string $keys     "id:base64key[,id:base64key...]", first = current; empty = derive
     * @param string $indexKey base64 key; empty = derive
     */
    public function __construct(
        private readonly string $environment,
        private readonly string $appKey,
        private readonly string $keys = '',
        private readonly string $indexKey = '',
    ) {
    }

    public function keys(): KeyMaterial
    {
        if ($this->environment === 'production') {
            throw new RuntimeException('The local PII key provider is not allowed in production; set PII_KEY_DRIVER=cloud_kms.');
        }

        $ring = $this->keys === '' ? ['local' => $this->derive('pii-encryption-v1')] : KeyRingParser::parse($this->keys, static fn (string $b64): string => self::decode($b64));
        $index = $this->indexKey === '' ? $this->derive('pii-blind-index-v1') : self::decode($this->indexKey);

        return new KeyMaterial($ring, (string) array_key_first($ring), $index);
    }

    private function derive(string $label): string
    {
        $base = str_starts_with($this->appKey, 'base64:') ? base64_decode(substr($this->appKey, 7), true) : $this->appKey;
        if (! is_string($base) || strlen($base) < 16) {
            throw new RuntimeException('APP_KEY is missing or too short to derive local PII keys; run `php artisan key:generate`.');
        }

        return hash_hkdf('sha256', $base, 32, $label);
    }

    private static function decode(string $base64): string
    {
        $raw = base64_decode($base64, true);
        if ($raw === false) {
            throw new RuntimeException('A local PII key is not valid base64.');
        }

        return $raw;
    }
}
