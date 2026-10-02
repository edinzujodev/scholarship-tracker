<?php

declare(strict_types=1);

namespace App\Http\JsonApi;

use App\Entities\Campaign;
use DateTimeInterface;

/** A single `campaigns` resource (GET /api/v1/campaigns/{campaignId}). */
final class CampaignResource
{
    /** @return array<string, mixed> */
    public static function toArray(Campaign $campaign): array
    {
        return [
            'type' => CampaignListDocument::TYPE,
            'id' => $campaign->id(),
            'attributes' => [
                'name' => $campaign->name(),
                'createdAt' => $campaign->createdAt()->format(DateTimeInterface::ATOM),
            ],
            'links' => [
                'self' => route('api.v1.campaigns.show', ['campaignId' => $campaign->id()]),
                'applicantSummary' => route('api.v1.campaigns.applicant-summary', ['campaignId' => $campaign->id()]),
            ],
        ];
    }
}
