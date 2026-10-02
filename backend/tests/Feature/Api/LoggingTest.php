<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Entities\AuditEntry;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Support\BuildsApplications;
use Tests\Support\CapturesLogs;
use Tests\Support\RefreshDoctrineSchema;
use Tests\TestCase;

final class LoggingTest extends TestCase
{
    use RefreshDoctrineSchema;
    use BuildsApplications;
    use CapturesLogs;

    // ------------------------------------------------------------ request id ---

    #[Test]
    public function every_response_carries_a_request_id_that_matches_its_log_lines(): void
    {
        $campaign = $this->campaign();

        $response = $this->getJson($this->summaryUrl($campaign->id()), $this->authHeaders())->assertOk();
        $id = $response->headers->get('X-Request-Id');

        $this->assertTrue(Str::isUuid($id));
        $this->assertSame($id, $this->lastLog('http')->context['request_id']);
    }

    #[Test]
    public function a_well_formed_incoming_request_id_is_reused_and_a_malformed_one_replaced(): void
    {
        $campaign = $this->campaign();

        $this->getJson($this->summaryUrl($campaign->id()), $this->authHeaders() + ['X-Request-Id' => 'portal-7f3c2a9e'])
            ->assertHeader('X-Request-Id', 'portal-7f3c2a9e');

        $replaced = $this->getJson($this->summaryUrl($campaign->id()), $this->authHeaders() + ['X-Request-Id' => "bad id\r\nX-Injected: 1"]);
        $this->assertTrue(Str::isUuid($replaced->headers->get('X-Request-Id')));
    }

    #[Test]
    public function error_documents_include_the_request_id_for_support(): void
    {
        $response = $this->getJson($this->summaryUrl((string) Str::uuid()), $this->authHeaders())->assertNotFound();

        $response->assertJsonPath('meta.requestId', $response->headers->get('X-Request-Id'));
    }

    // ------------------------------------------------------------ access log ---

    #[Test]
    public function the_access_log_records_route_status_partner_timing_and_query_names_only(): void
    {
        $campaign = $this->campaign();
        $this->application($campaign, 90);

        $this->getJson($this->summaryUrl($campaign->id(), 'min_score=80&sort=-aiScore'), $this->authHeaders())->assertOk();

        $entry = $this->lastLog('http');
        $this->assertSame('API request', $entry->message);
        $this->assertSame('INFO', $entry->level->getName());
        $this->assertSame('api.v1.campaigns.applicant-summary', $entry->context['route']);
        $this->assertSame('/api/v1/campaigns/{campaignId}/applicant-summary', $entry->context['path']);
        $this->assertSame(200, $entry->context['status']);
        $this->assertSame('partner-demo', $entry->context['partner_id']);
        $this->assertSame(['min_score', 'sort'], $entry->context['query_params']);
        $this->assertIsFloat($entry->context['duration_ms']);
        $this->assertGreaterThan(0, $entry->context['db_queries']);
        $this->assertStringNotContainsString($campaign->id(), json_encode($entry->context), 'paths are logged as route patterns');
    }

    #[Test]
    public function the_query_count_is_constant_across_page_sizes_no_n_plus_one(): void
    {
        $campaign = $this->campaign();
        for ($i = 0; $i < 12; $i++) {
            $this->application($campaign, 50 + $i);
        }

        $counts = [];
        foreach ([1, 12] as $size) {
            $this->em->clear();
            $this->getJson($this->summaryUrl($campaign->id(), "page[size]={$size}"), $this->authHeaders())->assertOk();
            $counts[$size] = $this->lastLog('http')->context['db_queries'];
        }

        $this->assertSame($counts[1], $counts[12], 'the summary must not run more queries for bigger pages');
        $this->assertLessThanOrEqual(5, $counts[12]);
    }

    #[Test]
    public function slow_queries_are_logged_without_parameter_values(): void
    {
        config(['logging.slow_query_ms' => 0]); // treat every query as slow
        $campaign = $this->campaign();
        $this->logs->clear(); // ignore the fixture's INSERT

        $this->getJson("/api/v1/campaigns/{$campaign->id()}", $this->authHeaders())->assertOk();

        $slow = $this->logRecords('db');
        $this->assertNotEmpty($slow);
        $this->assertSame('Slow query', $slow[0]->message);
        $this->assertStringContainsString('FROM campaigns', $slow[0]->context['sql']);
        $this->assertSame(2, $slow[0]->context['parameter_count']);
        $this->assertStringNotContainsString('partner-demo', json_encode($slow[0]->context), 'bound values are never logged');
    }

    // -------------------------------------------------------------- security ---

    #[Test]
    public function authentication_failures_go_to_the_security_channel_without_the_token(): void
    {
        $campaign = $this->campaign();

        $this->getJson($this->summaryUrl($campaign->id()), $this->authHeaders('stolen-or-wrong-token'))->assertUnauthorized();

        $entry = $this->lastLog('security');
        $this->assertSame('Authentication failed', $entry->message);
        $this->assertSame(401, $entry->context['status']);
        $this->assertStringNotContainsString('stolen-or-wrong-token', json_encode($this->logs->getRecords()));
    }

    #[Test]
    public function lookups_of_foreign_resources_are_recorded_as_security_events(): void
    {
        $foreign = $this->campaign(partnerId: 'partner-other');

        $this->getJson("/api/v1/campaigns/{$foreign->id()}", $this->authHeaders())->assertNotFound();

        $this->assertSame('Resource not found or not owned by caller', $this->lastLog('security')->message);
        $this->assertSame('partner-demo', $this->lastLog('security')->context['partner_id']);
    }

    // ---------------------------------------------------------------- errors ---

    #[Test]
    public function expected_client_errors_are_not_reported_as_application_errors(): void
    {
        $this->getJson($this->summaryUrl((string) Str::uuid()), $this->authHeaders())->assertNotFound();
        $this->getJson($this->summaryUrl((string) Str::uuid(), 'foo=1'), $this->authHeaders())->assertStatus(400);

        $this->assertSame([], $this->logRecords('app'));
        $this->assertSame('NOTICE', $this->lastLog('http')->level->getName());
    }

    #[Test]
    public function server_errors_are_reported_with_the_request_id_and_scrubbed(): void
    {
        Route::middleware(['api', 'partner.auth'])->get('api/v1/_test/boom', static function (): never {
            throw new RuntimeException('Upstream failed for ada@example.test with Bearer abc.def');
        });

        $response = $this->getJson('/api/v1/_test/boom', $this->authHeaders())->assertStatus(500);
        $id = $response->headers->get('X-Request-Id');

        $response->assertJsonPath('meta.requestId', $id)
            ->assertJsonPath('errors.0.detail', fn (string $detail) => ! str_contains($detail, 'ada@example.test') || config('app.debug'));

        $error = $this->lastLog('app');
        $this->assertSame('ERROR', $error->level->getName());
        $this->assertSame($id, $error->context['request_id']);
        $this->assertSame(RuntimeException::class, $error->context['exception']['class']);
        $this->assertSame('Upstream failed for [REDACTED] with Bearer [REDACTED]', $error->context['exception']['message']);
        $this->assertSame('ERROR', $this->lastLog('http')->level->getName());
    }

    // ----------------------------------------------------------------- audit ---

    #[Test]
    public function a_status_change_writes_one_audit_entry_in_the_database_and_the_audit_log(): void
    {
        $application = $this->application($this->campaign(), 90);

        $response = $this->patchJsonApi("/api/v1/applications/{$application->id()}", $this->statusBody($application->id(), 'shortlisted'))->assertOk();

        $this->em->clear();
        $entries = $this->em->getRepository(AuditEntry::class)->findBy(['subjectId' => $application->id()]);
        $this->assertCount(1, $entries);
        $this->assertSame(AuditEntry::APPLICATION_STATUS_CHANGED, $entries[0]->action());
        $this->assertSame('partner-demo', $entries[0]->partnerId());
        $this->assertSame(['from' => 'pending', 'to' => 'shortlisted'], $entries[0]->changes()['status']);
        $this->assertSame($response->headers->get('X-Request-Id'), $entries[0]->requestId());

        $log = $this->lastLog('audit');
        $this->assertSame('Application status changed', $log->message);
        $this->assertSame($entries[0]->id(), $log->context['audit_id']);
    }

    #[Test]
    public function no_op_and_rejected_changes_leave_no_audit_entry(): void
    {
        $application = $this->application($this->campaign(), 90);

        // Same status: idempotent, nothing written.
        $this->patchJsonApi("/api/v1/applications/{$application->id()}", $this->statusBody($application->id(), 'pending'))->assertOk();
        // Another partner: 404, nothing written.
        $this->patchJsonApi("/api/v1/applications/{$application->id()}", $this->statusBody($application->id(), 'rejected'), self::OTHER_TOKEN)->assertNotFound();
        // Invalid document: 422, nothing written.
        $this->patchJsonApi("/api/v1/applications/{$application->id()}", $this->statusBody($application->id(), 'hired'))->assertUnprocessable();

        $this->em->clear();
        $this->assertSame([], $this->em->getRepository(AuditEntry::class)->findAll());
        $this->assertSame([], $this->logRecords('audit'));
    }

    /** @return array<string, mixed> */
    private function statusBody(string $id, string $status): array
    {
        return ['data' => ['type' => 'applications', 'id' => $id, 'attributes' => ['status' => $status]]];
    }
}
