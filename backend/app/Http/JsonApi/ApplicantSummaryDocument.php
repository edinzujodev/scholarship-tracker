<?php

declare(strict_types=1);

namespace App\Http\JsonApi;

use App\Entities\Application;
use App\Enums\ScoreTier;
use App\Services\Dto\ApplicantSummary;
use Illuminate\Http\Request;

/**
 * Compound JSON:API document: `data` holds the campaign's `applications`,
 * each pointing at its `applicants` resource, and `included` carries every
 * referenced applicant exactly once (full linkage).
 */
final class ApplicantSummaryDocument
{
    /** @return array<string, mixed> */
    public static function fromSummary(ApplicantSummary $summary, Request $request): array
    {
        $metrics = $summary->metrics;
        $query = $summary->query;
        $lastPage = $summary->lastPage();

        return [
            'data' => array_map(ApplicationResource::toArray(...), $summary->applications),
            'included' => self::includedApplicants($summary->applications),
            'meta' => [
                'campaign' => [
                    'id' => $summary->campaign->id(),
                    'name' => $summary->campaign->name(),
                ],
                'summary' => [
                    'totalApplicants' => $metrics->totalApplicants,
                    'eligibleApplicants' => $metrics->eligibleApplicants,
                    'eligibleRate' => $metrics->eligibleRate,
                    'averageAiScore' => $metrics->averageAiScore,
                    'topTierCount' => $metrics->topTierCount,
                    'topTier' => array_map(
                        static fn (Application $a): array => ApplicationResource::highlight($a),
                        $metrics->topTier,
                    ),
                ],
                'filters' => [
                    'minScore' => $query->criteria->minScore,
                    'status' => $query->criteria->status?->value,
                    'appliedFrom' => $query->criteria->appliedFrom?->format('Y-m-d'),
                    'appliedTo' => $query->criteria->appliedTo?->format('Y-m-d'),
                ],
                'sort' => $query->sort->value,
                // Single source of truth for score thresholds; clients must not hard-code them.
                'thresholds' => ScoreTier::thresholds(),
                'page' => [
                    'number' => $query->pageNumber,
                    'size' => $query->pageSize,
                    'total' => $summary->filteredTotal,
                    'lastPage' => $lastPage,
                ],
            ],
            'links' => PaginationLinks::for($request, $query->pageNumber, $query->pageSize, $lastPage),
        ];
    }

    /**
     * @param list<Application> $applications
     * @return list<array<string, mixed>>
     */
    private static function includedApplicants(array $applications): array
    {
        $included = [];
        foreach ($applications as $application) {
            $applicant = $application->applicant();
            $included[$applicant->id()] ??= ApplicantResource::toArray($applicant);
        }

        return array_values($included);
    }
}
