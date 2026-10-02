<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsApplications;
use Tests\Support\RefreshDoctrineSchema;
use Tests\TestCase;

final class ApplicantSummaryTest extends TestCase
{
    use RefreshDoctrineSchema;
    use BuildsApplications;

    #[Test]
    public function it_aggregates_campaign_metrics_and_returns_a_json_api_document(): void
    {
        $campaign = $this->campaign();
        $this->application($campaign, 95, name: 'Ada Lovelace', highlights: ['Top 1% maths olympiad', 'Leadership', 'Published paper']);
        $this->application($campaign, 88, name: 'Grace Hopper');
        $this->application($campaign, 91, name: 'Alan Turing');
        $this->application($campaign, 86, name: 'Katherine Johnson');
        $this->application($campaign, 72, eligible: false);
        $this->application($campaign, 40, eligible: false);

        $response = $this->getJson($this->summaryUrl($campaign->id()), $this->authHeaders());

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.api+json')
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('jsonapi.version', '1.1')
            ->assertJsonPath('meta.campaign.id', $campaign->id())
            ->assertJsonPath('meta.summary.totalApplicants', 6)
            ->assertJsonPath('meta.summary.eligibleApplicants', 4)
            ->assertJsonPath('meta.summary.eligibleRate', 66.7)
            // (95 + 88 + 91 + 86 + 72 + 40) / 6 = 78.666… -> 78.67
            ->assertJsonPath('meta.summary.averageAiScore', 78.67)
            ->assertJsonPath('meta.summary.topTierCount', 4)
            ->assertJsonCount(4, 'meta.summary.topTier')
            ->assertJsonPath('meta.summary.topTier.0.name', 'Ada Lovelace')
            ->assertJsonPath('meta.summary.topTier.1.name', 'Alan Turing')
            ->assertJsonPath('meta.summary.topTier.2.name', 'Grace Hopper')
            ->assertJsonPath('meta.summary.topTier.3.name', 'Katherine Johnson')
            ->assertJsonPath('meta.sort', '-aiScore')
            ->assertJsonPath('meta.thresholds', ['topTier' => 85, 'strong' => 70, 'standoutAbove' => 90])
            ->assertJsonCount(2, 'meta.summary.topTier.0.aiHighlights')
            ->assertJsonCount(6, 'data')
            ->assertJsonPath('data.0.type', 'applications')
            ->assertJsonPath('data.0.relationships.applicant.data.type', 'applicants')
            ->assertJsonPath('data.0.relationships.campaign.data.id', $campaign->id())
            ->assertJsonPath('data.0.attributes.aiScore', 95)
            ->assertJsonPath('data.0.attributes.tier', 'top')
            ->assertJsonPath('data.0.attributes.status', 'pending')
            ->assertJsonPath('data.4.attributes.tier', 'strong')
            ->assertJsonPath('data.5.attributes.tier', 'review')
            ->assertJsonStructure([
                'data' => [['type', 'id', 'attributes' => ['aiScore', 'tier', 'eligible', 'status', 'aiHighlights', 'appliedAt'], 'relationships' => ['applicant' => ['data' => ['type', 'id']], 'campaign']]],
                'included' => [['type', 'id', 'attributes' => ['firstName', 'middleName', 'lastName']]],
                'meta' => ['summary' => ['topTier' => [['applicationId', 'applicantId', 'name', 'aiScore', 'aiHighlights']]], 'filters', 'page' => ['number', 'size', 'total', 'lastPage']],
                'links' => ['self', 'first', 'prev', 'next', 'last'],
            ]);

        // Compound document: every referenced applicant is included exactly once.
        $this->assertSame('Ada Lovelace', $this->applicantNames($response)[0]);
        $this->assertCount(6, $response->json('included'));
        $this->assertSame(['applicants'], array_values(array_unique(array_column($response->json('included'), 'type'))));

        // PII minimisation: email never leaves the API in list responses.
        $this->assertStringNotContainsString('@example.test', $response->getContent());
    }

    #[Test]
    public function min_score_filters_the_list_but_not_the_campaign_wide_summary(): void
    {
        $campaign = $this->campaign();
        foreach ([95, 85, 80, 79, 50] as $score) {
            $this->application($campaign, $score);
        }

        $this->getJson($this->summaryUrl($campaign->id(), 'min_score=80'), $this->authHeaders())
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.2.attributes.aiScore', 80)
            ->assertJsonPath('meta.filters.minScore', 80)
            ->assertJsonPath('meta.page.total', 3)
            ->assertJsonPath('meta.summary.totalApplicants', 5);
    }

    #[Test]
    public function it_paginates_with_json_api_page_parameters_and_links(): void
    {
        $campaign = $this->campaign();
        for ($score = 60; $score < 85; $score++) {
            $this->application($campaign, $score); // 25 applicants
        }

        $response = $this->getJson(
            $this->summaryUrl($campaign->id(), 'page[size]=10&page[number]=2&min_score=60'),
            $this->authHeaders(),
        );

        $response->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('data.0.attributes.aiScore', 74) // 84..75 on page 1
            ->assertJsonPath('meta.page', ['number' => 2, 'size' => 10, 'total' => 25, 'lastPage' => 3]);

        $links = $response->json('links');
        $this->assertStringContainsString('page%5Bnumber%5D=1', $links['prev']);
        $this->assertStringContainsString('page%5Bnumber%5D=3', $links['next']);
        $this->assertStringContainsString('min_score=60', $links['next'], 'filters must survive pagination links');

        $this->getJson($this->summaryUrl($campaign->id(), 'page[size]=10&page[number]=3'), $this->authHeaders())
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('links.next', null);
    }

    #[Test]
    public function pagination_is_stable_when_scores_tie(): void
    {
        $campaign = $this->campaign();
        for ($i = 0; $i < 7; $i++) {
            $this->application($campaign, 80);
        }

        $ids = [];
        foreach ([1, 2, 3, 4] as $page) {
            $ids = [...$ids, ...array_column(
                $this->getJson($this->summaryUrl($campaign->id(), "page[size]=2&page[number]={$page}"), $this->authHeaders())->json('data'),
                'id',
            )];
        }

        $this->assertCount(7, $ids);
        $this->assertCount(7, array_unique($ids), 'every applicant appears exactly once across pages');
    }

    #[Test]
    public function an_empty_campaign_returns_a_zeroed_summary_without_dividing_by_zero(): void
    {
        $campaign = $this->campaign();

        $this->getJson($this->summaryUrl($campaign->id(), 'min_score=50'), $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.summary', [
                'totalApplicants' => 0,
                'eligibleApplicants' => 0,
                'eligibleRate' => 0.0,
                'averageAiScore' => 0.0,
                'topTierCount' => 0,
                'topTier' => [],
            ])
            ->assertJsonPath('meta.page.total', 0)
            ->assertJsonPath('meta.page.lastPage', 1)
            ->assertJsonPath('links.next', null)
            ->assertJsonPath('links.prev', null);
    }

    #[Test]
    public function it_requires_a_bearer_token(): void
    {
        $campaign = $this->campaign();

        $this->getJson($this->summaryUrl($campaign->id()))
            ->assertUnauthorized()
            ->assertHeader('WWW-Authenticate', 'Bearer')
            ->assertJsonPath('errors.0.status', '401')
            ->assertJsonPath('errors.0.code', 'unauthenticated');

        $this->getJson($this->summaryUrl($campaign->id()), $this->authHeaders('not-a-real-token'))
            ->assertUnauthorized();
    }

    #[Test]
    public function another_partners_campaign_is_indistinguishable_from_a_missing_one(): void
    {
        $campaign = $this->campaign(partnerId: 'partner-demo');
        $this->application($campaign, 90);

        $foreign = $this->getJson($this->summaryUrl($campaign->id()), $this->authHeaders(self::OTHER_TOKEN));
        $missing = $this->getJson($this->summaryUrl((string) Str::uuid()), $this->authHeaders(self::OTHER_TOKEN));

        $foreign->assertNotFound()->assertJsonPath('errors.0.code', 'not_found');
        $missing->assertNotFound()->assertJsonPath('errors.0.code', 'not_found');
        $this->assertStringNotContainsString('Applicant', $foreign->getContent());
    }

    #[Test]
    #[DataProvider('invalidRequests')]
    public function invalid_input_returns_422_with_json_api_error_objects(string $campaignId, string $query, string $parameter): void
    {
        $this->campaign();
        $id = $campaignId === 'VALID' ? (string) Str::uuid() : $campaignId;

        $this->getJson($this->summaryUrl($id, $query), $this->authHeaders())
            ->assertUnprocessable()
            ->assertHeader('Content-Type', 'application/vnd.api+json')
            ->assertJsonPath('errors.0.status', '422')
            ->assertJsonPath('errors.0.code', 'validation_failed')
            ->assertJsonPath('errors.0.source.parameter', $parameter)
            ->assertJsonStructure(['errors' => [['status', 'code', 'title', 'detail', 'source']]]);
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function invalidRequests(): iterable
    {
        yield 'malformed campaign id' => ['not-a-uuid', '', 'campaignId'];
        yield 'sql-ish campaign id' => ["1' OR '1'='1", '', 'campaignId'];
        yield 'min_score above range' => ['VALID', 'min_score=150', 'min_score'];
        yield 'min_score not numeric' => ['VALID', 'min_score=high', 'min_score'];
        yield 'page size zero' => ['VALID', 'page[size]=0', 'page[size]'];
        yield 'page size too large' => ['VALID', 'page[size]=500', 'page[size]'];
        yield 'page number zero' => ['VALID', 'page[number]=0', 'page[number]'];
        yield 'unknown page key' => ['VALID', 'page[offset]=5', 'page'];
    }
}
