<?php

declare(strict_types=1);

namespace App\Services\Dto;

use App\Enums\ApplicationSort;

/**
 * Validated input for the applicant summary use case. Built by the HTTP layer
 * after validation, so every value here is already within bounds.
 */
final readonly class ApplicantSummaryQuery
{
    public function __construct(
        public string $campaignId,
        public string $partnerId,
        public ApplicationCriteria $criteria,
        public int $pageSize,
        public int $pageNumber,
        public ApplicationSort $sort = ApplicationSort::DEFAULT,
    ) {
    }

    public function offset(): int
    {
        return ($this->pageNumber - 1) * $this->pageSize;
    }
}
