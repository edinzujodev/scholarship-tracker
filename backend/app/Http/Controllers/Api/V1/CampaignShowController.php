<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\CampaignNotFound;
use App\Http\Controllers\Concerns\RespondsWithJsonApi;
use App\Http\Controllers\Controller;
use App\Http\JsonApi\CampaignResource;
use App\Http\Requests\ShowCampaignRequest;
use App\Repositories\CampaignRepository;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/v1/campaigns/{campaignId}
 *
 * One campaign, only if it belongs to the calling partner. The portal uses
 * it as a cheap access check before rendering a campaign page:
 * 422 malformed id, 401 bad token, 404 unknown or someone else's campaign.
 */
final class CampaignShowController extends Controller
{
    use RespondsWithJsonApi;

    public function __construct(private readonly CampaignRepository $campaigns)
    {
    }

    public function __invoke(ShowCampaignRequest $request): JsonResponse
    {
        $campaign = $this->campaigns->findForPartner($request->campaignId(), $request->partnerId())
            ?? throw CampaignNotFound::withId($request->campaignId());

        return $this->privateDocument(['data' => CampaignResource::toArray($campaign)]);
    }
}
