<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Entities\Applicant;
use App\Entities\Application;
use App\Entities\Campaign;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ApplicationTest extends TestCase
{
    #[Test]
    public function it_answers_for_its_applicant_and_campaign_without_callers_reaching_through_it(): void
    {
        $applicant = new Applicant('Ada', 'Lovelace', 'ada@example.test', middleName: 'King');
        $campaign = new Campaign('partner-demo', 'Scholars');
        $application = new Application($applicant, $campaign, 90, true, [], new DateTimeImmutable('2026-09-01'));

        $this->assertSame($applicant->id(), $application->applicantId());
        $this->assertSame('Ada King Lovelace', $application->applicantName());
        $this->assertSame($campaign->id(), $application->campaignId());
    }

    #[Test]
    public function the_ai_score_must_be_between_0_and_100(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Application(new Applicant('Ada', 'Lovelace', 'a@example.test'), new Campaign('p', 'c'), 101, true, [], new DateTimeImmutable());
    }
}
