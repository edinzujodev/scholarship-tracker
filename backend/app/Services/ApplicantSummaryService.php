<?php

declare(strict_types=1);

namespace App\Services;

use App\Entities\Campaign;
use App\Enums\ScoreTier;
use App\Exceptions\CampaignNotFound;
use App\Repositories\ApplicationRepository;
use App\Repositories\CampaignRepository;
use App\Services\Dto\ApplicantSummary;
use App\Services\Dto\ApplicantSummaryQuery;
use App\Services\Dto\CampaignMetrics;

/**
 * Query: the applicant summary for one campaign. Read-only; status changes
 * live in App\Actions\ChangeApplicationStatus.
 */
final class ApplicantSummaryService
{
    public const TOP_TIER_LIMIT = 5;

    public function __construct(
        private readonly CampaignRepository $campaigns,
        private readonly ApplicationRepository $applications,
    ) {
    }

    public function summarise(ApplicantSummaryQuery $query): ApplicantSummary
    {
        $campaign = $this->campaignFor($query->campaignId, $query->partnerId);

        $aggregates = $this->applications->aggregate($campaign, ScoreTier::TOP_THRESHOLD);
        $metrics = CampaignMetrics::fromAggregates(
            $aggregates['total'],
            $aggregates['eligible'],
            $aggregates['average'],
            $this->applications->topTier($campaign, ScoreTier::TOP_THRESHOLD, self::TOP_TIER_LIMIT),
            $aggregates['topTier'],
        );

        // Skip the list queries entirely for a campaign with no applications.
        if ($metrics->totalApplicants === 0) {
            return new ApplicantSummary($campaign, $metrics, [], 0, $query);
        }

        return new ApplicantSummary(
            campaign: $campaign,
            metrics: $metrics,
            applications: $this->applications->page($campaign, $query->criteria, $query->sort, $query->pageSize, $query->offset()),
            filteredTotal: $this->applications->countMatching($campaign, $query->criteria),
            query: $query,
        );
    }

    private function campaignFor(string $campaignId, string $partnerId): Campaign
    {
        return $this->campaigns->findForPartner($campaignId, $partnerId)
            ?? throw CampaignNotFound::withId($campaignId);
    }
}
