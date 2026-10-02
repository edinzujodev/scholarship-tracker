<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Shared vocabulary for AI match scores. The single source of the score
 * thresholds: the API sends each application's `tier` and the numbers in
 * meta.thresholds, and the frontend reads them from there.
 */
enum ScoreTier: string
{
    case Top = 'top';        // >= 85
    case Strong = 'strong';  // >= 70
    case Review = 'review';  // < 70

    public const TOP_THRESHOLD = 85;
    public const STRONG_THRESHOLD = 70;
    /** Scores strictly above this are highlighted as standouts in the portal. */
    public const STANDOUT_ABOVE = 90;

    public static function fromScore(int $score): self
    {
        return match (true) {
            $score >= self::TOP_THRESHOLD => self::Top,
            $score >= self::STRONG_THRESHOLD => self::Strong,
            default => self::Review,
        };
    }

    /**
     * Published in the applicant summary (meta.thresholds) so clients never
     * hard-code these numbers: the backend is the single source of truth.
     *
     * @return array{topTier: int, strong: int, standoutAbove: int}
     */
    public static function thresholds(): array
    {
        return [
            'topTier' => self::TOP_THRESHOLD,
            'strong' => self::STRONG_THRESHOLD,
            'standoutAbove' => self::STANDOUT_ABOVE,
        ];
    }
}
