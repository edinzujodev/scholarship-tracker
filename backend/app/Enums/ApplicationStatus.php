<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Review state of one application (an applicant's submission to one campaign).
 * It lives on the application, not the applicant: the same person can be
 * shortlisted in one campaign and rejected in another.
 */
enum ApplicationStatus: string
{
    case Pending = 'pending';
    case Shortlisted = 'shortlisted';
    case Rejected = 'rejected';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
