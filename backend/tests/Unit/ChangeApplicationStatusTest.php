<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Actions\ChangeApplicationStatus;
use App\Audit\AuditTrail;
use App\Entities\Applicant;
use App\Entities\Application;
use App\Entities\Campaign;
use App\Enums\ApplicationStatus;
use App\Exceptions\ApplicationNotFound;
use App\Repositories\ApplicationRepository;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class ChangeApplicationStatusTest extends TestCase
{
    #[Test]
    public function it_changes_and_saves_the_status(): void
    {
        $application = $this->application();
        $repository = $this->createMock(ApplicationRepository::class);
        $repository->method('findForPartner')->with($application->id(), 'partner-demo')->willReturn($application);
        $repository->expects($this->once())->method('save')->with($application);
        $audit = $this->createMock(AuditTrail::class);
        $audit->expects($this->once())->method('record')->with($this->callback(
            fn ($entry) => $entry->changes()['status'] === ['from' => 'pending', 'to' => 'shortlisted']
                && $entry->partnerId() === 'partner-demo'
                && $entry->subjectId() === $application->id(),
        ));

        $result = (new ChangeApplicationStatus($repository, $audit, new NullLogger()))($application->id(), 'partner-demo', ApplicationStatus::Shortlisted);

        $this->assertSame(ApplicationStatus::Shortlisted, $result->status());
    }

    #[Test]
    public function repeating_the_current_status_writes_and_audits_nothing(): void
    {
        $application = $this->application();
        $repository = $this->createMock(ApplicationRepository::class);
        $repository->method('findForPartner')->willReturn($application);
        $repository->expects($this->never())->method('save');
        $audit = $this->createMock(AuditTrail::class);
        $audit->expects($this->never())->method('record');

        (new ChangeApplicationStatus($repository, $audit, new NullLogger()))($application->id(), 'partner-demo', ApplicationStatus::Pending);
    }

    #[Test]
    public function an_application_outside_the_partners_campaigns_is_not_found(): void
    {
        $repository = $this->createMock(ApplicationRepository::class);
        $repository->method('findForPartner')->willReturn(null);
        $repository->expects($this->never())->method('save');

        $this->expectException(ApplicationNotFound::class);

        (new ChangeApplicationStatus($repository, $this->createStub(AuditTrail::class), new NullLogger()))('9b2f0f7e-8d1c-4b2a-9d59-6c1f1b7f7a01', 'partner-other', ApplicationStatus::Rejected);
    }

    private function application(): Application
    {
        return new Application(
            applicant: new Applicant('Ada', 'Lovelace', 'ada@example.test'),
            campaign: new Campaign('partner-demo', 'Scholars'),
            aiScore: 90,
            eligible: true,
            aiHighlights: [],
            appliedAt: new DateTimeImmutable('2026-09-01'),
        );
    }
}
