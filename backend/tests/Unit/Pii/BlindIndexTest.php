<?php

declare(strict_types=1);

namespace Tests\Unit\Pii;

use App\Security\Pii\BlindIndex;
use App\Security\Pii\KeyMaterial;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BlindIndexTest extends TestCase
{
    private static function index(string $indexKeyByte = 'i'): BlindIndex
    {
        return new BlindIndex(new KeyMaterial(['k1' => str_repeat('k', 32)], 'k1', str_repeat($indexKeyByte, 32)));
    }

    #[Test]
    public function it_is_deterministic_and_ignores_case_and_surrounding_spaces(): void
    {
        $index = self::index();

        $this->assertSame($index->of('applicants.email', 'Ada@Example.test'), $index->of('applicants.email', ' ada@example.test '));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $index->of('applicants.email', 'ada@example.test'));
    }

    #[Test]
    public function it_is_keyed_and_scoped_per_field(): void
    {
        $index = self::index();
        $value = 'ada@example.test';

        $this->assertNotSame(hash('sha256', $value), $index->of('applicants.email', $value), 'must not be a plain hash');
        $this->assertNotSame($index->of('applicants.email', $value), self::index('j')->of('applicants.email', $value));
        $this->assertNotSame($index->of('applicants.email', $value), $index->of('other.field', $value));
    }
}
