<?php

declare(strict_types=1);

namespace App\Services\Dto;

use App\Entities\Application;

/**
 * Campaign-wide aggregates. Always safe to render: an empty campaign yields
 * zeroed values rather than nulls or a division by zero.
 */
final readonly class CampaignMetrics
{
    /**
     * @param list<Application> $topTier the highest-scoring top-tier applications (capped)
     */
    public function __construct(
        public int $totalApplicants,
        public int $eligibleApplicants,
        public float $averageAiScore,
        public float $eligibleRate,
        public array $topTier,
        public int $topTierCount = 0,
    ) {
    }

    /**
     * @param int|string|null   $total    raw COUNT() value (drivers may return strings)
     * @param int|string|null   $eligible raw SUM() value (NULL when there are no rows)
     * @param float|string|null $average  raw AVG() value (NULL when there are no rows)
     * @param list<Application> $topTier
     * @param int|string|null   $topTierCount raw SUM() of applicants at or above the top-tier score
     */
    public static function fromAggregates(
        int|string|null $total,
        int|string|null $eligible,
        float|string|null $average,
        array $topTier = [],
        int|string|null $topTierCount = null,
    ): self {
        $total = (int) ($total ?? 0);
        $eligible = (int) ($eligible ?? 0);

        return new self(
            totalApplicants: $total,
            eligibleApplicants: $eligible,
            averageAiScore: $total > 0 && $average !== null ? round((float) $average, 2) : 0.0,
            eligibleRate: $total > 0 ? round($eligible / $total * 100, 1) : 0.0,
            topTier: $topTier,
            topTierCount: (int) ($topTierCount ?? count($topTier)),
        );
    }

    public static function empty(): self
    {
        return self::fromAggregates(0, 0, null);
    }
}
