<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\RespondsWithJsonApi;
use App\Http\Controllers\Controller;
use App\Http\JsonApi\CampaignListDocument;
use App\Http\Requests\CampaignIndexRequest;
use App\Services\CampaignListService;
use Illuminate\Http\JsonResponse;

final class CampaignIndexController extends Controller
{
    use RespondsWithJsonApi;

    public function __construct(private readonly CampaignListService $service)
    {
    }

    /** GET /api/v1/campaigns */
    public function __invoke(CampaignIndexRequest $request): JsonResponse
    {
        $result = $this->service->forPartner($request->partnerId(), $request->pageSize(), $request->pageNumber());

        return $this->privateDocument(
            CampaignListDocument::from($result['items'], $result['total'], $request->pageNumber(), $request->pageSize(), $request),
        );
    }
}
