<?php

declare(strict_types=1);

namespace App\Security\Pii;

use Illuminate\Http\Client\Factory as Http;
use RuntimeException;

/**
 * Envelope encryption with Google Cloud KMS.
 *
 * The data keys (DEKs) are configured only in *wrapped* form: encrypted by a
 * key-encryption key that never leaves Cloud KMS. At first use the process
 * asks KMS to unwrap them (projects.locations.keyRings.cryptoKeys.decrypt)
 * and keeps the plaintext DEKs in memory only. Access is controlled and
 * audited by KMS IAM (roles/cloudkms.cryptoKeyDecrypter on the service
 * account); revoking it makes the stored data unreadable.
 *
 * One KMS call per data key per process, not per field.
 */
final class CloudKmsKeyProvider implements KeyProvider
{
    private ?KeyMaterial $cached = null;

    /**
     * @param string $wrappedKeys "id:base64wrapped[,id:...]", first = current
     */
    public function __construct(
        private readonly Http $http,
        private readonly string $keyName,
        private readonly string $wrappedKeys,
        private readonly string $wrappedIndexKey,
        private readonly string $endpoint = 'https://cloudkms.googleapis.com/v1',
        private readonly string $accessToken = '',
        private readonly string $metadataTokenUrl = 'http://metadata.google.internal/computeMetadata/v1/instance/service-accounts/default/token',
    ) {
    }

    public function keys(): KeyMaterial
    {
        if ($this->cached !== null) {
            return $this->cached;
        }

        if (preg_match('#^projects/[^/]+/locations/[^/]+/keyRings/[^/]+/cryptoKeys/[^/]+$#', $this->keyName) !== 1) {
            throw new RuntimeException('PII_KMS_KEY_NAME must be a full Cloud KMS crypto key name.');
        }
        if ($this->wrappedIndexKey === '') {
            throw new RuntimeException('PII_KMS_WRAPPED_INDEX_KEY is required.');
        }

        $token = $this->token();
        $ring = KeyRingParser::parse($this->wrappedKeys, fn (string $wrapped): string => $this->unwrap($wrapped, $token));

        return $this->cached = new KeyMaterial($ring, (string) array_key_first($ring), $this->unwrap($this->wrappedIndexKey, $token));
    }

    private function unwrap(string $wrappedBase64, string $token): string
    {
        $response = $this->http
            ->withToken($token)
            ->acceptJson()
            ->timeout(5)
            ->retry(2, 200, throw: false)
            ->post(rtrim($this->endpoint, '/')."/{$this->keyName}:decrypt", ['ciphertext' => $wrappedBase64]);

        if (! $response->successful()) {
            // Never include the response body: it could echo key material.
            throw new RuntimeException("Cloud KMS refused to unwrap a PII data key (HTTP {$response->status()}).");
        }

        $plaintext = base64_decode((string) $response->json('plaintext'), true);
        if ($plaintext === false || strlen($plaintext) !== 32) {
            throw new RuntimeException('Cloud KMS returned an unexpected PII data key (expected 32 bytes).');
        }

        return $plaintext;
    }

    private function token(): string
    {
        if ($this->accessToken !== '') {
            return $this->accessToken;
        }

        $response = $this->http
            ->withHeaders(['Metadata-Flavor' => 'Google'])
            ->timeout(3)
            ->get($this->metadataTokenUrl);
        $token = $response->successful() ? $response->json('access_token') : null;
        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Could not obtain a Google access token from the metadata server for Cloud KMS.');
        }

        return $token;
    }
}
