<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\RespondsWithJsonApi;
use App\Http\Controllers\Controller;
use App\Http\JsonApi\ApplicantSummaryDocument;
use App\Http\Requests\ApplicantSummaryRequest;
use App\Services\ApplicantSummaryService;
use Illuminate\Http\JsonResponse;

final class ApplicantSummaryController extends Controller
{
    use RespondsWithJsonApi;

    public function __construct(private readonly ApplicantSummaryService $service)
    {
    }

    /** GET /api/v1/campaigns/{campaignId}/applicant-summary */
    public function __invoke(ApplicantSummaryRequest $request): JsonResponse
    {
        $summary = $this->service->summarise($request->toQuery());

        return $this->privateDocument(ApplicantSummaryDocument::fromSummary($summary, $request));
    }
}
