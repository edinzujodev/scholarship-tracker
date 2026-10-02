<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\ApplicationStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsApplications;
use Tests\Support\RefreshDoctrineSchema;
use Tests\TestCase;

final class ApplicantSummaryFilteringTest extends TestCase
{
    use RefreshDoctrineSchema;
    use BuildsApplications;

    #[Test]
    public function it_filters_by_status(): void
    {
        $campaign = $this->campaign();
        $this->application($campaign, 90, name: 'Shortlisted One')->changeStatus(ApplicationStatus::Shortlisted);
        $this->application($campaign, 70, name: 'Rejected One')->changeStatus(ApplicationStatus::Rejected);
        $this->application($campaign, 80, name: 'Pending One');
        $this->em->flush();

        $response = $this->getJson($this->summaryUrl($campaign->id(), 'filter[status]=shortlisted'), $this->authHeaders())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.filters.status', 'shortlisted')
            ->assertJsonPath('meta.page.total', 1)
            ->assertJsonPath('meta.summary.totalApplicants', 3);

        $this->assertSame(['Shortlisted One'], $this->applicantNames($response));
    }

    #[Test]
    public function it_filters_by_an_inclusive_application_date_range(): void
    {
        $campaign = $this->campaign();
        $this->application($campaign, 80, name: 'Before', appliedAt: '2026-08-31 23:59:59');
        $this->application($campaign, 80, name: 'First day', appliedAt: '2026-09-01 00:00:00');
        $this->application($campaign, 80, name: 'Last day', appliedAt: '2026-09-10 23:59:59');
        $this->application($campaign, 80, name: 'After', appliedAt: '2026-09-11 00:00:00');

        $response = $this->getJson(
            $this->summaryUrl($campaign->id(), 'filter[applied_from]=2026-09-01&filter[applied_to]=2026-09-10&sort=appliedAt'),
            $this->authHeaders(),
        );

        $response->assertOk()
            ->assertJsonPath('meta.filters.appliedFrom', '2026-09-01')
            ->assertJsonPath('meta.filters.appliedTo', '2026-09-10');
        $this->assertSame(['First day', 'Last day'], $this->applicantNames($response));
    }

    #[Test]
    public function filters_combine_with_min_score_and_survive_pagination_links(): void
    {
        $campaign = $this->campaign();
        foreach ([95, 91, 88, 60] as $score) {
            $this->application($campaign, $score)->changeStatus(ApplicationStatus::Shortlisted);
        }
        $this->application($campaign, 99);
        $this->em->flush();

        $response = $this->getJson(
            $this->summaryUrl($campaign->id(), 'min_score=85&filter[status]=shortlisted&page[size]=2'),
            $this->authHeaders(),
        );

        $response->assertOk()
            ->assertJsonPath('meta.page.total', 3)
            ->assertJsonPath('meta.page.lastPage', 2);
        $this->assertStringContainsString('filter%5Bstatus%5D=shortlisted', $response->json('links.next'));
        $this->assertStringContainsString('min_score=85', $response->json('links.next'));
    }

    #[Test]
    #[DataProvider('sorts')]
    public function it_sorts_by_the_json_api_sort_parameter(string $sort, array $expectedNames): void
    {
        $campaign = $this->campaign();
        $this->application($campaign, 70, name: 'Charlie Carter', appliedAt: '2026-09-03 10:00:00');
        $this->application($campaign, 95, name: 'Alice Adams', appliedAt: '2026-09-01 10:00:00');
        $this->application($campaign, 82, name: 'Bob Brown', appliedAt: '2026-09-02 10:00:00');

        $response = $this->getJson($this->summaryUrl($campaign->id(), "sort={$sort}"), $this->authHeaders());

        $response->assertOk()->assertJsonPath('meta.sort', $sort);
        $this->assertSame($expectedNames, $this->applicantNames($response));
    }

    /** @return iterable<string, array{string, list<string>}> */
    public static function sorts(): iterable
    {
        yield 'score high to low' => ['-aiScore', ['Alice Adams', 'Bob Brown', 'Charlie Carter']];
        yield 'score low to high' => ['aiScore', ['Charlie Carter', 'Bob Brown', 'Alice Adams']];
        yield 'newest first' => ['-appliedAt', ['Charlie Carter', 'Bob Brown', 'Alice Adams']];
        yield 'oldest first' => ['appliedAt', ['Alice Adams', 'Bob Brown', 'Charlie Carter']];
        yield 'name a-z' => ['name', ['Alice Adams', 'Bob Brown', 'Charlie Carter']];
        yield 'name z-a' => ['-name', ['Charlie Carter', 'Bob Brown', 'Alice Adams']];
    }

    #[Test]
    public function sorting_by_name_orders_by_last_name_then_first_name(): void
    {
        $campaign = $this->campaign();
        $this->application($campaign, 70, name: 'Zoe Adams');
        $this->application($campaign, 80, name: 'Ada King Lovelace');
        $this->application($campaign, 90, name: 'Alan Adams');

        $ascending = $this->getJson($this->summaryUrl($campaign->id(), 'sort=name'), $this->authHeaders())->assertOk();
        $descending = $this->getJson($this->summaryUrl($campaign->id(), 'sort=-name'), $this->authHeaders())->assertOk();

        $this->assertSame(['Alan Adams', 'Zoe Adams', 'Ada King Lovelace'], $this->applicantNames($ascending));
        $this->assertSame(['Ada King Lovelace', 'Zoe Adams', 'Alan Adams'], $this->applicantNames($descending));
        $this->assertContains(
            ['firstName' => 'Ada', 'middleName' => 'King', 'lastName' => 'Lovelace'],
            array_column($ascending->json('included'), 'attributes'),
        );
    }

    #[Test]
    public function an_unsupported_sort_is_a_400_bad_request(): void
    {
        $campaign = $this->campaign();

        $this->getJson($this->summaryUrl($campaign->id(), 'sort=email'), $this->authHeaders())
            ->assertStatus(400)
            ->assertJsonPath('errors.0.status', '400')
            ->assertJsonPath('errors.0.code', 'unsupported_sort')
            ->assertJsonPath('errors.0.source.parameter', 'sort');
    }

    #[Test]
    #[DataProvider('invalidFilters')]
    public function invalid_filters_return_422(string $query, string $parameter): void
    {
        $campaign = $this->campaign();

        $this->getJson($this->summaryUrl($campaign->id(), $query), $this->authHeaders())
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.source.parameter', $parameter);
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidFilters(): iterable
    {
        yield 'unknown status' => ['filter[status]=hired', 'filter[status]'];
        yield 'bad date format' => ['filter[applied_from]=01/09/2026', 'filter[applied_from]'];
        yield 'range ends before it starts' => ['filter[applied_from]=2026-09-10&filter[applied_to]=2026-09-01', 'filter[applied_to]'];
        yield 'unknown filter key' => ['filter[email]=x', 'filter'];
    }
}
