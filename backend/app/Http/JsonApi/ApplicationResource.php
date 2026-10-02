<?php

declare(strict_types=1);

namespace App\Http\JsonApi;

use App\Entities\Application;
use App\Enums\ScoreTier;
use DateTimeInterface;

/**
 * JSON:API `applications` resource: one applicant's submission to one campaign.
 * The person is a relationship to an `applicants` resource, which is sent once
 * in the top-level `included` array (compound document).
 */
final class ApplicationResource
{
    public const TYPE = 'applications';

    /** @return array<string, mixed> */
    public static function toArray(Application $application): array
    {
        return [
            'type' => self::TYPE,
            'id' => $application->id(),
            'attributes' => [
                'aiScore' => $application->aiScore(),
                'tier' => ScoreTier::fromScore($application->aiScore())->value,
                'eligible' => $application->isEligible(),
                'status' => $application->status()->value,
                'aiHighlights' => array_values($application->aiHighlights()),
                'appliedAt' => $application->appliedAt()->format(DateTimeInterface::ATOM),
            ],
            'relationships' => [
                'applicant' => [
                    'data' => ['type' => ApplicantResource::TYPE, 'id' => $application->applicantId()],
                ],
                'campaign' => [
                    'data' => ['type' => CampaignListDocument::TYPE, 'id' => $application->campaignId()],
                ],
            ],
        ];
    }

    /**
     * Compact form used in meta.summary.topTier. Email is intentionally
     * omitted from all list responses (PII minimisation).
     *
     * @return array<string, mixed>
     */
    public static function highlight(Application $application): array
    {
        return [
            'applicationId' => $application->id(),
            'applicantId' => $application->applicantId(),
            // Ready-to-display name for the top-tier preview ("First Middle Last").
            'name' => $application->applicantName(),
            'aiScore' => $application->aiScore(),
            'aiHighlights' => array_slice(array_values($application->aiHighlights()), 0, 2),
        ];
    }
}
