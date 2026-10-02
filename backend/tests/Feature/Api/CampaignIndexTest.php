<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\ApplicationStatus;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsApplications;
use Tests\Support\RefreshDoctrineSchema;
use Tests\TestCase;

final class CampaignIndexTest extends TestCase
{
    use RefreshDoctrineSchema;
    use BuildsApplications;

    #[Test]
    public function it_lists_only_the_partners_own_campaigns_with_applicant_counts(): void
    {
        $scholars = $this->campaign(name: 'Scholars');
        $this->application($scholars, 90)->changeStatus(ApplicationStatus::Shortlisted);
        $this->application($scholars, 70, eligible: false)->changeStatus(ApplicationStatus::Rejected);
        $this->application($scholars, 80);
        $this->em->flush();
        $empty = $this->campaign(name: 'Empty');
        $foreign = $this->campaign(partnerId: 'partner-other', name: 'Someone else');

        $response = $this->getJson('/api/v1/campaigns', $this->authHeaders());

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.api+json')
            // RespondsWithJsonApi: partner data must never be stored by a shared cache.
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.page.total', 2)
            ->assertJsonStructure([
                'data' => [['type', 'id', 'attributes' => ['name', 'createdAt', 'applicantCount', 'eligibleCount', 'eligibleRate', 'averageAiScore', 'pendingCount', 'shortlistedCount', 'rejectedCount'], 'links' => ['applicantSummary']]],
                'meta' => ['page'],
                'links' => ['self', 'first', 'prev', 'next', 'last'],
            ]);

        $byName = collect($response->json('data'))->keyBy('attributes.name');
        $this->assertFalse($byName->has('Someone else'));
        $this->assertStringNotContainsString($foreign->id(), $response->getContent());

        $this->assertSame('campaigns', $byName['Scholars']['type']);
        $this->assertSame([
            'applicantCount' => 3, 'eligibleCount' => 2, 'eligibleRate' => 66.7, 'averageAiScore' => 80.0, 'pendingCount' => 1, 'shortlistedCount' => 1, 'rejectedCount' => 1,
        ], array_intersect_key($byName['Scholars']['attributes'], array_flip(['applicantCount', 'eligibleCount', 'eligibleRate', 'averageAiScore', 'pendingCount', 'shortlistedCount', 'rejectedCount'])));
        $this->assertStringEndsWith("/api/v1/campaigns/{$scholars->id()}/applicant-summary", $byName['Scholars']['links']['applicantSummary']);

        // A campaign with no applicants gets zeroes, not nulls.
        $this->assertSame(0, $byName['Empty']['attributes']['applicantCount']);
        $this->assertSame(0, $byName['Empty']['attributes']['eligibleCount']);
        $this->assertEquals(0.0, $byName['Empty']['attributes']['eligibleRate']);
        $this->assertEquals(0.0, $byName['Empty']['attributes']['averageAiScore']);
        $this->assertSame($empty->id(), $byName['Empty']['id']);
    }

    #[Test]
    public function it_paginates_campaigns(): void
    {
        foreach (['A', 'B', 'C'] as $name) {
            $this->campaign(name: $name);
        }

        $this->getJson('/api/v1/campaigns?page[size]=2&page[number]=2', $this->authHeaders())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.page', ['number' => 2, 'size' => 2, 'total' => 3, 'lastPage' => 2]);
    }

    #[Test]
    public function a_partner_with_no_campaigns_gets_an_empty_list(): void
    {
        $this->campaign(partnerId: 'partner-demo');

        $this->getJson('/api/v1/campaigns', $this->authHeaders(self::OTHER_TOKEN))
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.page.total', 0);
    }

    #[Test]
    public function it_requires_authentication_and_valid_paging(): void
    {
        $this->getJson('/api/v1/campaigns')->assertUnauthorized();

        $this->getJson('/api/v1/campaigns?page[size]=0', $this->authHeaders())
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.source.parameter', 'page[size]');
    }
}
