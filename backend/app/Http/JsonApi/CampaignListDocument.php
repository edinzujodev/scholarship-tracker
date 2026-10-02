<?php

declare(strict_types=1);

namespace App\Http\JsonApi;

use App\Services\Dto\CampaignOverview;
use DateTimeInterface;
use Illuminate\Http\Request;

final class CampaignListDocument
{
    public const TYPE = 'campaigns';

    /**
     * @param list<CampaignOverview> $items
     * @return array<string, mixed>
     */
    public static function from(array $items, int $total, int $pageNumber, int $pageSize, Request $request): array
    {
        $lastPage = max(1, (int) ceil($total / $pageSize));

        return [
            'data' => array_map(static fn (CampaignOverview $o): array => self::resource($o), $items),
            'meta' => [
                'page' => ['number' => $pageNumber, 'size' => $pageSize, 'total' => $total, 'lastPage' => $lastPage],
            ],
            'links' => PaginationLinks::for($request, $pageNumber, $pageSize, $lastPage),
        ];
    }

    /** @return array<string, mixed> */
    private static function resource(CampaignOverview $o): array
    {
        $id = $o->campaign->id();

        return [
            'type' => self::TYPE,
            'id' => $id,
            'attributes' => [
                'name' => $o->campaign->name(),
                'createdAt' => $o->campaign->createdAt()->format(DateTimeInterface::ATOM),
                'applicantCount' => $o->applicantCount,
                'eligibleCount' => $o->eligibleCount,
                'eligibleRate' => $o->eligibleRate,
                'averageAiScore' => $o->averageAiScore,
                'pendingCount' => $o->pendingCount,
                'shortlistedCount' => $o->shortlistedCount,
                'rejectedCount' => $o->rejectedCount,
            ],
            // Where to go next: the per-campaign applicant summary endpoint.
            'links' => [
                'applicantSummary' => route('api.v1.campaigns.applicant-summary', ['campaignId' => $id]),
            ],
        ];
    }
}
