<?php

declare(strict_types=1);

namespace Tests\Unit\Pii;

use App\Security\Pii\FieldCipher;
use App\Security\Pii\KeyMaterial;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class FieldCipherTest extends TestCase
{
    private static function keys(string ...$ids): KeyMaterial
    {
        $ring = [];
        foreach ($ids as $id) {
            $ring[$id] = str_repeat(substr($id, 0, 1), 32);
        }

        return new KeyMaterial($ring, $ids[0], str_repeat('i', 32));
    }

    #[Test]
    public function it_round_trips_unicode_text(): void
    {
        $cipher = new FieldCipher(self::keys('k1'));

        $stored = $cipher->encrypt('Zoë Ñúñez-O\'Brien');

        $this->assertStringStartsWith('v1.k1.', $stored);
        $this->assertStringNotContainsString('Zo', $stored);
        $this->assertSame('Zoë Ñúñez-O\'Brien', $cipher->decrypt($stored));
    }

    #[Test]
    public function the_same_plaintext_encrypts_differently_each_time(): void
    {
        $cipher = new FieldCipher(self::keys('k1'));

        $this->assertNotSame($cipher->encrypt('ada@example.test'), $cipher->encrypt('ada@example.test'));
    }

    #[Test]
    public function a_tampered_value_is_rejected(): void
    {
        $cipher = new FieldCipher(self::keys('k1'));
        [$version, $keyId, $payload] = explode('.', $cipher->encrypt('Ada'));
        $raw = base64_decode($payload, true);
        $raw[strlen($raw) - 1] = chr(ord($raw[strlen($raw) - 1]) ^ 1);

        $this->expectException(RuntimeException::class);
        $cipher->decrypt("{$version}.{$keyId}.".base64_encode($raw));
    }

    #[Test]
    public function values_written_under_an_older_key_stay_readable_after_rotation(): void
    {
        $old = (new FieldCipher(self::keys('k1')))->encrypt('Ada');
        $rotated = new FieldCipher(self::keys('k2', 'k1'));

        $this->assertSame('Ada', $rotated->decrypt($old));
        $this->assertSame('k2', FieldCipher::keyIdOf($rotated->encrypt('Ada')));
    }

    #[Test]
    public function an_unknown_key_id_or_foreign_format_is_rejected(): void
    {
        $stored = (new FieldCipher(self::keys('k1')))->encrypt('Ada');
        $other = new FieldCipher(self::keys('k2'));

        $this->assertThrowsRuntime(fn () => $other->decrypt($stored));
        $this->assertThrowsRuntime(fn () => $other->decrypt('plain text'));
        $this->assertThrowsRuntime(fn () => $other->decrypt('v1.k2.!!!'));
    }

    #[Test]
    public function key_material_rejects_weak_or_shared_keys(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new KeyMaterial(['k1' => str_repeat('a', 32)], 'k1', str_repeat('a', 32));
    }

    private function assertThrowsRuntime(callable $call): void
    {
        try {
            $call();
            $this->fail('Expected a RuntimeException.');
        } catch (RuntimeException) {
            $this->addToAssertionCount(1);
        }
    }
}
