<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\CampaignRepository;
use App\Services\Dto\CampaignOverview;

final class CampaignListService
{
    public function __construct(private readonly CampaignRepository $campaigns)
    {
    }

    /**
     * Only ever returns the authenticated partner's own campaigns.
     *
     * @return array{items: list<CampaignOverview>, total: int}
     */
    public function forPartner(string $partnerId, int $pageSize, int $pageNumber): array
    {
        $total = $this->campaigns->countForPartner($partnerId);

        return [
            'items' => $total === 0 ? [] : $this->campaigns->overviewForPartner($partnerId, $pageSize, ($pageNumber - 1) * $pageSize),
            'total' => $total,
        ];
    }
}
