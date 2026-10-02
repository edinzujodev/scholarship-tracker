<?php

declare(strict_types=1);

namespace App\Actions;

use App\Audit\AuditTrail;
use App\Entities\Application;
use App\Entities\AuditEntry;
use App\Enums\ApplicationStatus;
use App\Exceptions\ApplicationNotFound;
use App\Repositories\ApplicationRepository;
use App\Support\RequestId;
use Psr\Log\LoggerInterface;

/**
 * Command: change one application's review status.
 *
 * Kept apart from ApplicantSummaryService (which only reads) so reads and
 * writes change for different reasons (single responsibility, command-query
 * separation). Status belongs to the application, so the same person can be
 * shortlisted in one campaign and rejected in another.
 *
 * Every real change is recorded in the audit trail in the same transaction.
 */
final class ChangeApplicationStatus
{
    public function __construct(
        private readonly ApplicationRepository $applications,
        private readonly AuditTrail $audit,
        /** The `audit` log channel (bound in AppServiceProvider). */
        private readonly LoggerInterface $auditLog,
    ) {
    }

    /**
     * @throws ApplicationNotFound when the application does not exist or its
     *                             campaign belongs to another partner (same 404)
     */
    public function __invoke(string $applicationId, string $partnerId, ApplicationStatus $status): Application
    {
        $application = $this->applications->findForPartner($applicationId, $partnerId)
            ?? throw ApplicationNotFound::withId($applicationId);

        // Idempotent: repeating the current status writes nothing, audits nothing.
        $from = $application->status();
        if ($from === $status) {
            return $application;
        }

        $application->changeStatus($status);
        $entry = AuditEntry::applicationStatusChanged($application, $partnerId, $from, $status, RequestId::current());
        $this->audit->record($entry);
        $this->applications->save($application); // one flush: status + audit entry

        $this->auditLog->info('Application status changed', [
            'audit_id' => $entry->id(),
            'partner_id' => $partnerId,
            'application_id' => $application->id(),
            'campaign_id' => $application->campaignId(),
            'from' => $from->value,
            'to' => $status->value,
        ]);

        return $application;
    }
}
