<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Entities\Applicant;
use App\Entities\Application;
use App\Entities\Campaign;
use DateTimeImmutable;
use Illuminate\Testing\TestResponse;

/**
 * Tiny fixture builders. Doctrine has no Eloquent-style factories, so these
 * persist entities directly through the test EntityManager.
 */
trait BuildsApplications
{
    protected const PARTNER = 'partner-demo';
    protected const TOKEN = 'demo-partner-token';
    protected const OTHER_TOKEN = 'other-partner-token';

    protected function campaign(string $partnerId = self::PARTNER, string $name = 'Autumn Scholarship 2026'): Campaign
    {
        $campaign = new Campaign($partnerId, $name);
        $this->em->persist($campaign);
        $this->em->flush();

        return $campaign;
    }

    protected function person(?string $name = null): Applicant
    {
        static $sequence = 0;
        $sequence++;

        // "Ada Lovelace" → first "Ada", last "Lovelace"; "Ada King Lovelace" also sets the middle name.
        $parts = explode(' ', $name ?? "Applicant {$sequence}");
        $first = array_shift($parts);
        $last = array_pop($parts) ?? 'Unknown';
        $applicant = new Applicant(
            firstName: $first,
            lastName: $last,
            email: "person{$sequence}@example.test",
            middleName: $parts === [] ? null : implode(' ', $parts),
        );
        $this->em->persist($applicant);
        $this->em->flush();

        return $applicant;
    }

    /**
     * Creates an application to $campaign, for a new person unless $applicant is given.
     *
     * @param list<string> $highlights
     */
    protected function application(
        Campaign $campaign,
        int $score,
        bool $eligible = true,
        ?string $name = null,
        array $highlights = ['Strong STEM background'],
        string $appliedAt = '2026-09-01 10:00:00',
        ?Applicant $applicant = null,
    ): Application {
        $application = new Application(
            applicant: $applicant ?? $this->person($name),
            campaign: $campaign,
            aiScore: $score,
            eligible: $eligible,
            aiHighlights: $highlights,
            appliedAt: new DateTimeImmutable($appliedAt),
        );
        $this->em->persist($application);
        $this->em->flush();

        return $application;
    }

    /** @return array<string, string> */
    protected function authHeaders(string $token = self::TOKEN): array
    {
        return [
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/vnd.api+json',
        ];
    }

    /**
     * PATCH a JSON:API document with the JSON:API media type (plain
     * application/json is rejected with 415).
     *
     * @param array<string, mixed> $document
     */
    protected function patchJsonApi(string $uri, array $document, string $token = self::TOKEN): TestResponse
    {
        return $this->json('PATCH', $uri, $document, $this->authHeaders($token) + ['Content-Type' => 'application/vnd.api+json']);
    }

    protected function summaryUrl(string $campaignId, string $query = ''): string
    {
        return "/api/v1/campaigns/{$campaignId}/applicant-summary".($query !== '' ? "?{$query}" : '');
    }

    /**
     * Applicant names for the `data` applications, in order, resolved through
     * the compound document's `included` applicants.
     *
     * @return list<string>
     */
    protected function applicantNames(TestResponse $response): array
    {
        $people = [];
        foreach ($response->json('included') ?? [] as $resource) {
            $a = $resource['attributes'];
            $people[$resource['type'].':'.$resource['id']] = implode(' ', array_filter([$a['firstName'], $a['middleName'], $a['lastName']]));
        }

        return array_map(
            static function (array $application) use ($people): string {
                $ref = $application['relationships']['applicant']['data'];

                return $people[$ref['type'].':'.$ref['id']];
            },
            $response->json('data') ?? [],
        );
    }
}
