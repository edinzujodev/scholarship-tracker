<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Dto\CampaignMetrics;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CampaignMetricsTest extends TestCase
{
    #[Test]
    public function null_aggregates_from_an_empty_table_become_zeroes(): void
    {
        // What COUNT/SUM/AVG return for zero rows on MySQL and SQLite.
        $metrics = CampaignMetrics::fromAggregates(0, null, null);

        $this->assertSame(0, $metrics->totalApplicants);
        $this->assertSame(0, $metrics->eligibleApplicants);
        $this->assertSame(0.0, $metrics->averageAiScore);
        $this->assertSame(0.0, $metrics->eligibleRate);
        $this->assertSame([], $metrics->topTier);
    }

    #[Test]
    public function string_values_from_the_pdo_driver_are_cast_and_rounded(): void
    {
        $metrics = CampaignMetrics::fromAggregates('3', '2', '78.6666666667');

        $this->assertSame(3, $metrics->totalApplicants);
        $this->assertSame(2, $metrics->eligibleApplicants);
        $this->assertSame(78.67, $metrics->averageAiScore);
        $this->assertSame(66.7, $metrics->eligibleRate);
    }

    #[Test]
    public function the_empty_factory_is_safe_to_render(): void
    {
        $this->assertEquals(CampaignMetrics::fromAggregates(0, 0, null), CampaignMetrics::empty());
    }
}
