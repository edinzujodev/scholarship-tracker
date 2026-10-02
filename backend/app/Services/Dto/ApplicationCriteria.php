<?php

declare(strict_types=1);

namespace App\Services\Dto;

use App\Enums\ApplicationStatus;
use DateTimeImmutable;

/**
 * Filters for a campaign's applications. Date bounds are whole UTC days:
 * `appliedFrom` is inclusive from 00:00, `appliedTo` inclusive through the end of that day.
 */
final readonly class ApplicationCriteria
{
    public function __construct(
        public ?int $minScore = null,
        public ?ApplicationStatus $status = null,
        public ?DateTimeImmutable $appliedFrom = null,
        public ?DateTimeImmutable $appliedTo = null,
    ) {
    }

    /** Exclusive upper bound: the start of the day after `appliedTo`. */
    public function appliedBefore(): ?DateTimeImmutable
    {
        return $this->appliedTo?->modify('+1 day');
    }
}
