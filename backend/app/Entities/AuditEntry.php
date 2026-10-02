<?php

declare(strict_types=1);

namespace App\Entities;

use App\Entities\Concerns\HasUuid;
use App\Enums\ApplicationStatus;
use App\Support\Guard;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Append-only business audit record: who changed what, from what to what,
 * when, and in which request. Written in the same transaction as the change,
 * so the trail can never miss (or invent) a change. There are no setters.
 */
#[ORM\Entity]
#[ORM\Table(name: 'audit_log')]
#[ORM\Index(name: 'idx_audit_subject', columns: ['subject_type', 'subject_id'])]
#[ORM\Index(name: 'idx_audit_partner_time', columns: ['partner_id', 'occurred_at'])]
class AuditEntry
{
    use HasUuid;

    public const APPLICATION_STATUS_CHANGED = 'application.status_changed';

    #[ORM\Column(name: 'occurred_at', type: 'datetime_immutable')]
    private DateTimeImmutable $occurredAt;

    /**
     * @param array<string, mixed> $changes
     */
    public function __construct(
        #[ORM\Column(name: 'partner_id', type: 'string', length: 64)]
        private string $partnerId,
        #[ORM\Column(type: 'string', length: 64)]
        private string $action,
        #[ORM\Column(name: 'subject_type', type: 'string', length: 32)]
        private string $subjectType,
        #[ORM\Column(name: 'subject_id', type: 'guid')]
        private string $subjectId,
        #[ORM\Column(type: 'json')]
        private array $changes,
        #[ORM\Column(name: 'request_id', type: 'string', length: 64, nullable: true)]
        private ?string $requestId = null,
    ) {
        $this->partnerId = Guard::requiredText($partnerId, 'Partner id', 64);
        $this->action = Guard::requiredText($action, 'Audit action', 64);
        $this->subjectType = Guard::requiredText($subjectType, 'Audit subject type', 32);
        $this->requestId = Guard::optionalText($requestId, 'Request id', 64);
        $this->assignId(null);
        $this->occurredAt = new DateTimeImmutable();
    }

    public static function applicationStatusChanged(
        Application $application,
        string $partnerId,
        ApplicationStatus $from,
        ApplicationStatus $to,
        ?string $requestId,
    ): self {
        return new self(
            partnerId: $partnerId,
            action: self::APPLICATION_STATUS_CHANGED,
            subjectType: 'applications',
            subjectId: $application->id(),
            changes: ['status' => ['from' => $from->value, 'to' => $to->value], 'campaignId' => $application->campaignId()],
            requestId: $requestId,
        );
    }

    public function partnerId(): string
    {
        return $this->partnerId;
    }

    public function action(): string
    {
        return $this->action;
    }

    public function subjectType(): string
    {
        return $this->subjectType;
    }

    public function subjectId(): string
    {
        return $this->subjectId;
    }

    /** @return array<string, mixed> */
    public function changes(): array
    {
        return $this->changes;
    }

    public function requestId(): ?string
    {
        return $this->requestId;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
