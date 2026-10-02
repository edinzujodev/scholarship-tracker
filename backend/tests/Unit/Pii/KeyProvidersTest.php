<?php

declare(strict_types=1);

namespace Tests\Unit\Pii;

use App\Security\Pii\CloudKmsKeyProvider;
use App\Security\Pii\LocalKeyProvider;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class KeyProvidersTest extends TestCase
{
    private const KEY_NAME = 'projects/owl/locations/europe-west1/keyRings/pii/cryptoKeys/applicants';

    #[Test]
    public function the_local_provider_is_refused_in_production(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('cloud_kms');
        (new LocalKeyProvider('production', 'base64:'.base64_encode(random_bytes(32))))->keys();
    }

    #[Test]
    public function the_local_provider_derives_separate_stable_keys_from_app_key(): void
    {
        $appKey = 'base64:'.base64_encode(str_repeat('x', 32));

        $keys = (new LocalKeyProvider('local', $appKey))->keys();

        $this->assertSame('local', $keys->currentKeyId);
        $this->assertNotSame($keys->encryptionKeys['local'], $keys->indexKey);
        $this->assertSame($keys->indexKey, (new LocalKeyProvider('testing', $appKey))->keys()->indexKey);
    }

    #[Test]
    public function the_local_provider_reads_an_explicit_key_ring_in_order(): void
    {
        $keys = (new LocalKeyProvider(
            'local',
            '',
            'k2:'.base64_encode(str_repeat('b', 32)).',k1:'.base64_encode(str_repeat('a', 32)),
            base64_encode(str_repeat('i', 32)),
        ))->keys();

        $this->assertSame('k2', $keys->currentKeyId);
        $this->assertSame(['k2', 'k1'], array_keys($keys->encryptionKeys));
    }

    #[Test]
    public function cloud_kms_unwraps_each_data_key_once_with_a_metadata_server_token(): void
    {
        $http = new Factory();
        $http->fake([
            'metadata.google.internal/*' => $http->response(['access_token' => 'ya29.test', 'expires_in' => 3599]),
            // The fake KMS "unwraps" by padding the wrapped marker to 32 bytes.
            'cloudkms.googleapis.com/*' => fn (Request $request) => $http->response([
                'plaintext' => base64_encode(str_pad((string) $request['ciphertext'], 32, '#')),
            ]),
        ]);
        $provider = new CloudKmsKeyProvider($http, self::KEY_NAME, 'k2:wrapped-k2,k1:wrapped-k1', 'wrapped-index');

        $keys = $provider->keys();
        $provider->keys(); // cached: no second round of calls

        $this->assertSame('k2', $keys->currentKeyId);
        $this->assertSame(str_pad('wrapped-k1', 32, '#'), $keys->encryptionKeys['k1']);
        $this->assertSame(str_pad('wrapped-index', 32, '#'), $keys->indexKey);
        $http->assertSentCount(4); // 1 token + 3 unwraps
        $http->assertSent(fn (Request $r) => $r->url() === 'https://cloudkms.googleapis.com/v1/'.self::KEY_NAME.':decrypt'
            && $r->hasHeader('Authorization', 'Bearer ya29.test'));
        $http->assertSent(fn (Request $r) => str_contains($r->url(), 'metadata.google.internal')
            && $r->hasHeader('Metadata-Flavor', 'Google'));
    }

    #[Test]
    public function cloud_kms_errors_do_not_leak_the_response_body(): void
    {
        $http = new Factory();
        $http->fake(['*' => $http->response(['error' => 'secret-detail'], 403)]);
        $provider = new CloudKmsKeyProvider($http, self::KEY_NAME, 'k1:wrapped', 'wrapped-index', accessToken: 'token');

        try {
            $provider->keys();
            $this->fail('Expected a RuntimeException.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('HTTP 403', $e->getMessage());
            $this->assertStringNotContainsString('secret-detail', $e->getMessage());
        }
    }

    #[Test]
    public function cloud_kms_requires_a_full_crypto_key_name(): void
    {
        $this->expectExceptionMessage('PII_KMS_KEY_NAME');
        (new CloudKmsKeyProvider(new Factory(), 'applicants', 'k1:wrapped', 'wrapped-index', accessToken: 'token'))->keys();
    }

    #[Test]
    public function cloud_kms_rejects_a_key_of_the_wrong_length(): void
    {
        $http = new Factory();
        $http->fake(['*' => $http->response(['plaintext' => base64_encode('too short')])]);

        $this->expectExceptionMessage('expected 32 bytes');
        (new CloudKmsKeyProvider($http, self::KEY_NAME, 'k1:wrapped', 'wrapped-index', accessToken: 'token'))->keys();
    }
}
