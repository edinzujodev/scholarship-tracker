<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\ScoreTier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ScoreTierTest extends TestCase
{
    #[Test]
    #[DataProvider('boundaries')]
    public function it_maps_scores_to_tiers_at_the_boundaries(int $score, ScoreTier $expected): void
    {
        $this->assertSame($expected, ScoreTier::fromScore($score));
    }

    /** @return iterable<string, array{int, ScoreTier}> */
    public static function boundaries(): iterable
    {
        yield 'zero' => [0, ScoreTier::Review];
        yield 'just below strong' => [69, ScoreTier::Review];
        yield 'strong threshold' => [70, ScoreTier::Strong];
        yield 'just below top' => [84, ScoreTier::Strong];
        yield 'top threshold' => [85, ScoreTier::Top];
        yield 'perfect' => [100, ScoreTier::Top];
    }
}
