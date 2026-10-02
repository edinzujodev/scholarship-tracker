<?php

declare(strict_types=1);

namespace App\Services\Dto;

use App\Entities\Application;
use App\Entities\Campaign;

final readonly class ApplicantSummary
{
    /**
     * @param list<Application> $applications the current page (applicants fetch-joined), filtered and ordered
     */
    public function __construct(
        public Campaign $campaign,
        public CampaignMetrics $metrics,
        public array $applications,
        public int $filteredTotal,
        public ApplicantSummaryQuery $query,
    ) {
    }

    public function lastPage(): int
    {
        return max(1, (int) ceil($this->filteredTotal / $this->query->pageSize));
    }
}
