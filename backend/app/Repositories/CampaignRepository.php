<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Entities\Campaign;
use App\Services\Dto\CampaignOverview;

interface CampaignRepository
{
    /**
     * Returns the campaign only when it belongs to the given partner, so callers
     * cannot tell "does not exist" apart from "belongs to someone else".
     */
    public function findForPartner(string $campaignId, string $partnerId): ?Campaign;

    /**
     * The partner's campaigns with applicant counts, newest first. One grouped
     * query, no N+1.
     *
     * @return list<CampaignOverview>
     */
    public function overviewForPartner(string $partnerId, int $limit, int $offset): array;

    public function countForPartner(string $partnerId): int;
}
