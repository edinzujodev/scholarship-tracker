<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Entities\Campaign;
use App\Exceptions\CampaignNotFound;
use App\Repositories\ApplicationRepository;
use App\Repositories\CampaignRepository;
use App\Services\ApplicantSummaryService;
use App\Services\Dto\ApplicationCriteria;
use App\Services\Dto\ApplicantSummaryQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ApplicantSummaryServiceTest extends TestCase
{
    #[Test]
    public function an_empty_campaign_skips_the_list_queries(): void
    {
        $campaign = new Campaign('partner-demo', 'Empty');

        $campaigns = $this->createStub(CampaignRepository::class);
        $campaigns->method('findForPartner')->willReturn($campaign);

        $applicants = $this->createMock(ApplicationRepository::class);
        $applicants->method('aggregate')->willReturn(['total' => 0, 'eligible' => null, 'average' => null, 'topTier' => null]);
        $applicants->method('topTier')->willReturn([]);
        $applicants->expects($this->never())->method('page');
        $applicants->expects($this->never())->method('countMatching');

        $summary = (new ApplicantSummaryService($campaigns, $applicants))->summarise($this->query($campaign->id()));

        $this->assertSame([], $summary->applications);
        $this->assertSame(0, $summary->filteredTotal);
        $this->assertSame(1, $summary->lastPage());
        $this->assertSame(0.0, $summary->metrics->averageAiScore);
    }

    #[Test]
    public function the_campaign_lookup_is_always_scoped_to_the_partner(): void
    {
        $campaigns = $this->createMock(CampaignRepository::class);
        $campaigns->expects($this->once())
            ->method('findForPartner')
            ->with('9b2f0f7e-8d1c-4b2a-9d59-6c1f1b7f7a01', 'partner-other')
            ->willReturn(null);

        $this->expectException(CampaignNotFound::class);

        (new ApplicantSummaryService($campaigns, $this->createStub(ApplicationRepository::class)))
            ->summarise($this->query('9b2f0f7e-8d1c-4b2a-9d59-6c1f1b7f7a01', 'partner-other'));
    }

    private function query(string $campaignId, string $partnerId = 'partner-demo'): ApplicantSummaryQuery
    {
        return new ApplicantSummaryQuery($campaignId, $partnerId, new ApplicationCriteria(), 10, 1);
    }
}
