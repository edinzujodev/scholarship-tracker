<?php

declare(strict_types=1);

namespace App\Services\Dto;

use App\Entities\Campaign;

/** One row of the campaign list: the campaign plus its application counts. */
final readonly class CampaignOverview
{
    public function __construct(
        public Campaign $campaign,
        public int $applicantCount,
        public int $eligibleCount,
        public float $eligibleRate,
        public float $averageAiScore,
        public int $pendingCount,
        public int $shortlistedCount,
        public int $rejectedCount,
    ) {
    }

    /**
     * @param array{applicantCount?: int|string|null, eligibleCount?: int|string|null, averageAiScore?: float|string|null, pendingCount?: int|string|null, shortlistedCount?: int|string|null, rejectedCount?: int|string|null} $row
     *        raw aggregates (drivers may return strings; SUM/AVG are NULL for a campaign with no applications)
     */
    public static function fromRow(Campaign $campaign, array $row): self
    {
        $count = (int) ($row['applicantCount'] ?? 0);
        $eligible = (int) ($row['eligibleCount'] ?? 0);

        return new self(
            campaign: $campaign,
            applicantCount: $count,
            eligibleCount: $eligible,
            // Same rounding as the campaign summary; no division by zero for an empty campaign.
            eligibleRate: $count > 0 ? round($eligible / $count * 100, 1) : 0.0,
            averageAiScore: $count > 0 && isset($row['averageAiScore']) ? round((float) $row['averageAiScore'], 2) : 0.0,
            pendingCount: (int) ($row['pendingCount'] ?? 0),
            shortlistedCount: (int) ($row['shortlistedCount'] ?? 0),
            rejectedCount: (int) ($row['rejectedCount'] ?? 0),
        );
    }
}
