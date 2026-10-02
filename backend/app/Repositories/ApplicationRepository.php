<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Entities\Application;
use App\Entities\Campaign;
use App\Enums\ApplicationSort;
use App\Services\Dto\ApplicationCriteria;

interface ApplicationRepository
{
    /**
     * Single aggregate query over all of the campaign's applications.
     *
     * @return array{total: int|string|null, eligible: int|string|null, average: float|string|null, topTier: int|string|null}
     */
    public function aggregate(Campaign $campaign, int $topTierScore): array;

    /** @return list<Application> highest scores first, applicant fetch-joined */
    public function topTier(Campaign $campaign, int $minScore, int $limit): array;

    /** @return list<Application> applicant fetch-joined (no N+1 when rendering names) */
    public function page(Campaign $campaign, ApplicationCriteria $criteria, ApplicationSort $sort, int $limit, int $offset): array;

    public function countMatching(Campaign $campaign, ApplicationCriteria $criteria): int;

    /**
     * Returns the application only when its campaign belongs to the partner,
     * so "does not exist" and "belongs to someone else" look the same.
     */
    public function findForPartner(string $applicationId, string $partnerId): ?Application;

    public function save(Application $application): void;
}
