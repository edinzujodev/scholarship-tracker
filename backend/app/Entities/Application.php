<?php

declare(strict_types=1);

namespace App\Entities;

use App\Entities\Concerns\HasUuid;
use App\Enums\ApplicationStatus;
use App\Support\Guard;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * One applicant's submission to one campaign, and its AI review.
 * An applicant can apply to a given campaign at most once.
 */
#[ORM\Entity]
#[ORM\Table(name: 'applications')]
#[ORM\UniqueConstraint(name: 'uniq_applications_applicant_campaign', columns: ['applicant_id', 'campaign_id'])]
#[ORM\Index(name: 'idx_applications_campaign_score', columns: ['campaign_id', 'ai_score'])]
#[ORM\Index(name: 'idx_applications_campaign_status', columns: ['campaign_id', 'status'])]
// Serves filter[applied_from]/filter[applied_to] and sort=±appliedAt within a campaign.
#[ORM\Index(name: 'idx_applications_campaign_applied', columns: ['campaign_id', 'applied_at'])]
class Application
{
    use HasUuid;

    #[ORM\Column(type: 'string', length: 20, enumType: ApplicationStatus::class)]
    private ApplicationStatus $status = ApplicationStatus::Pending;

    /**
     * @param list<string> $aiHighlights
     */
    public function __construct(
        #[ORM\ManyToOne(targetEntity: Applicant::class)]
        #[ORM\JoinColumn(name: 'applicant_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
        private Applicant $applicant,
        #[ORM\ManyToOne(targetEntity: Campaign::class)]
        #[ORM\JoinColumn(name: 'campaign_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
        private Campaign $campaign,
        #[ORM\Column(name: 'ai_score', type: 'smallint')]
        private int $aiScore,
        #[ORM\Column(type: 'boolean')]
        private bool $eligible,
        #[ORM\Column(name: 'ai_highlights', type: 'json')]
        private array $aiHighlights,
        #[ORM\Column(name: 'applied_at', type: 'datetime_immutable')]
        private DateTimeImmutable $appliedAt,
        ?string $id = null,
    ) {
        Guard::intBetween($aiScore, 'AI score', 0, 100);
        $this->aiHighlights = Guard::textList($aiHighlights, 'AI highlights', maxItems: 20, maxLength: 500);

        $this->assignId($id);
    }

    public function applicant(): Applicant
    {
        return $this->applicant;
    }

    public function campaign(): Campaign
    {
        return $this->campaign;
    }

    /*
     * Law of Demeter: callers ask the application, not the application's
     * applicant or campaign. Reading an id does not load a lazy Doctrine proxy.
     */

    public function applicantId(): string
    {
        return $this->applicant->id();
    }

    public function applicantName(): string
    {
        return $this->applicant->fullName();
    }

    public function campaignId(): string
    {
        return $this->campaign->id();
    }

    public function aiScore(): int
    {
        return $this->aiScore;
    }

    public function isEligible(): bool
    {
        return $this->eligible;
    }

    /** @return list<string> */
    public function aiHighlights(): array
    {
        return $this->aiHighlights;
    }

    public function appliedAt(): DateTimeImmutable
    {
        return $this->appliedAt;
    }

    public function status(): ApplicationStatus
    {
        return $this->status;
    }

    public function changeStatus(ApplicationStatus $status): void
    {
        $this->status = $status;
    }
}
