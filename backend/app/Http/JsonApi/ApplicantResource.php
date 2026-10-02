<?php

declare(strict_types=1);

namespace App\Http\JsonApi;

use App\Entities\Applicant;

/**
 * JSON:API `applicants` resource: the person. Only the name parts are exposed; email
 * stays server-side (PII minimisation).
 */
final class ApplicantResource
{
    public const TYPE = 'applicants';

    /** @return array<string, mixed> */
    public static function toArray(Applicant $applicant): array
    {
        return [
            'type' => self::TYPE,
            'id' => $applicant->id(),
            'attributes' => [
                'firstName' => $applicant->firstName(),
                'middleName' => $applicant->middleName(),
                'lastName' => $applicant->lastName(),
            ],
        ];
    }
}
