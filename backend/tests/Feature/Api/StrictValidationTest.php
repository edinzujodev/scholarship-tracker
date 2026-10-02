<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsApplications;
use Tests\Support\RefreshDoctrineSchema;
use Tests\TestCase;

/**
 * Strict input validation across every endpoint: unknown parameters and
 * members are rejected, numbers must be canonical, bodies must be well-formed
 * JSON:API documents sent with the JSON:API media type.
 */
final class StrictValidationTest extends TestCase
{
    use RefreshDoctrineSchema;
    use BuildsApplications;

    // ---------------------------------------------------------------- query ---

    #[Test]
    #[DataProvider('unknownQueryParameters')]
    public function unknown_query_parameters_are_a_400(string $endpoint, string $query, string $parameter): void
    {
        $campaign = $this->campaign();
        $url = str_replace('{campaign}', $campaign->id(), $endpoint).'?'.$query;

        $this->getJson($url, $this->authHeaders())
            ->assertStatus(400)
            ->assertJsonPath('errors.0.status', '400')
            ->assertJsonPath('errors.0.code', 'unsupported_parameter')
            ->assertJsonPath('errors.0.source.parameter', $parameter);
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function unknownQueryParameters(): iterable
    {
        yield 'summary: made-up parameter' => ['/api/v1/campaigns/{campaign}/applicant-summary', 'foo=1', 'foo'];
        yield 'summary: JSON:API include (unsupported)' => ['/api/v1/campaigns/{campaign}/applicant-summary', 'include=applicant', 'include'];
        yield 'summary: JSON:API sparse fieldsets (unsupported)' => ['/api/v1/campaigns/{campaign}/applicant-summary', 'fields[applications]=aiScore', 'fields'];
        yield 'campaign list: sort (unsupported here)' => ['/api/v1/campaigns', 'sort=name', 'sort'];
        yield 'campaign show: any parameter' => ['/api/v1/campaigns/{campaign}', 'page[size]=1', 'page'];
    }

    #[Test]
    public function every_unknown_parameter_is_reported(): void
    {
        $campaign = $this->campaign();

        $response = $this->getJson("/api/v1/campaigns/{$campaign->id()}/applicant-summary?foo=1&bar=2", $this->authHeaders())
            ->assertStatus(400);

        $this->assertSame(['foo', 'bar'], array_column(array_column($response->json('errors'), 'source'), 'parameter'));
    }

    #[Test]
    #[DataProvider('nonCanonicalNumbers')]
    public function numbers_must_be_plain_non_negative_integers(string $query, string $parameter): void
    {
        $campaign = $this->campaign();

        $this->getJson("/api/v1/campaigns/{$campaign->id()}/applicant-summary?{$query}", $this->authHeaders())
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.source.parameter', $parameter);
    }

    /** @return iterable<string, array{string, string}> */
    public static function nonCanonicalNumbers(): iterable
    {
        yield 'plus sign' => ['min_score=%2B80', 'min_score'];
        yield 'leading space' => ['min_score=%2080', 'min_score'];
        yield 'decimal' => ['min_score=80.0', 'min_score'];
        yield 'exponent' => ['min_score=8e1', 'min_score'];
        yield 'empty value' => ['min_score=', 'min_score'];
        yield 'leading zero' => ['min_score=080', 'min_score'];
        yield 'array instead of scalar' => ['min_score[]=80', 'min_score'];
        yield 'page size with plus sign' => ['page[size]=%2B5', 'page[size]'];
        yield 'page number negative' => ['page[number]=-1', 'page[number]'];
    }

    #[Test]
    #[DataProvider('invalidScalars')]
    public function filter_and_sort_values_must_be_exact(string $query, string $parameter, int $status): void
    {
        $campaign = $this->campaign();

        $this->getJson("/api/v1/campaigns/{$campaign->id()}/applicant-summary?{$query}", $this->authHeaders())
            ->assertStatus($status)
            ->assertJsonPath('errors.0.source.parameter', $parameter);
    }

    /** @return iterable<string, array{string, string, int}> */
    public static function invalidScalars(): iterable
    {
        yield 'status in wrong case' => ['filter[status]=Shortlisted', 'filter[status]', 422];
        yield 'empty status' => ['filter[status]=', 'filter[status]', 422];
        yield 'impossible date' => ['filter[applied_from]=2026-02-30', 'filter[applied_from]', 422];
        yield 'date with time' => ['filter[applied_from]=2026-09-01T00:00:00', 'filter[applied_from]', 422];
        yield 'sort with two fields' => ['sort=name,-aiScore', 'sort', 400];
        yield 'sort as array' => ['sort[]=name', 'sort', 400];
    }

    // ----------------------------------------------------------------- body ---

    #[Test]
    public function request_bodies_must_use_the_json_api_media_type(): void
    {
        $application = $this->application($this->campaign(), 90);

        // Plain application/json is not accepted for writes: strict JSON:API.
        $this->patchJson("/api/v1/applications/{$application->id()}", $this->statusBody($application->id(), 'rejected'), $this->authHeaders())
            ->assertStatus(415)
            ->assertJsonPath('errors.0.code', 'unsupported_media_type');
    }

    #[Test]
    #[DataProvider('malformedBodies')]
    public function malformed_json_bodies_are_a_400(string $raw): void
    {
        $application = $this->application($this->campaign(), 90);

        $this->call('PATCH', "/api/v1/applications/{$application->id()}", server: $this->jsonApiServer(), content: $raw)
            ->assertStatus(400)
            ->assertJsonPath('errors.0.code', 'malformed_body');
    }

    /** @return iterable<string, array{string}> */
    public static function malformedBodies(): iterable
    {
        yield 'not JSON' => ['data=1'];
        yield 'truncated JSON' => ['{"data": {"type": "applications"'];
        yield 'JSON array, not an object' => ['[1, 2, 3]'];
        yield 'JSON scalar' => ['"rejected"'];
        yield 'empty body' => [''];
    }

    #[Test]
    #[DataProvider('unknownMembers')]
    public function unknown_document_members_are_rejected_with_a_pointer(array $body, string $pointer): void
    {
        $application = $this->application($this->campaign(), 90);
        $body = array_replace_recursive($this->statusBody($application->id(), 'rejected'), $body);

        $this->patchJsonApi("/api/v1/applications/{$application->id()}", $body)
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.source.pointer', $pointer);

        $this->em->clear();
        $reloadedApplication = $this->em->find($application::class, $application->id());
        $this->assertNotNull($reloadedApplication, 'application must still exist');
        $this->assertSame('pending', $reloadedApplication->status()->value, 'nothing may be written');
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function unknownMembers(): iterable
    {
        yield 'unknown top-level member' => [['included' => []], '/included'];
        yield 'relationships are read-only' => [['data' => ['relationships' => ['applicant' => ['data' => null]]]], '/data/relationships'];
        yield 'read-only attribute' => [['data' => ['attributes' => ['aiScore' => 100]]], '/data/attributes/aiScore'];
    }

    #[Test]
    #[DataProvider('invalidStatusDocuments')]
    public function the_status_document_is_validated_strictly(array $data, string $pointer): void
    {
        $application = $this->application($this->campaign(), 90);
        $data = array_replace(['type' => 'applications', 'id' => $application->id()], $data);

        $this->patchJsonApi("/api/v1/applications/{$application->id()}", ['data' => $data])
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.source.pointer', $pointer);
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function invalidStatusDocuments(): iterable
    {
        yield 'status in wrong case' => [['attributes' => ['status' => 'Rejected']], '/data/attributes/status'];
        yield 'status as number' => [['attributes' => ['status' => 1]], '/data/attributes/status'];
        yield 'status null' => [['attributes' => ['status' => null]], '/data/attributes/status'];
        yield 'attributes missing' => [[], '/data/attributes'];
        yield 'attributes empty' => [['attributes' => []], '/data/attributes'];
        yield 'id is not a UUID' => [['id' => 'abc', 'attributes' => ['status' => 'rejected']], '/data/id'];
        yield 'type is not a string' => [['type' => ['applications'], 'attributes' => ['status' => 'rejected']], '/data/type'];
    }

    #[Test]
    public function query_parameters_on_a_write_are_rejected(): void
    {
        $application = $this->application($this->campaign(), 90);

        $this->patchJsonApi("/api/v1/applications/{$application->id()}?notify=1", $this->statusBody($application->id(), 'rejected'))
            ->assertStatus(400)
            ->assertJsonPath('errors.0.source.parameter', 'notify');
    }

    #[Test]
    public function a_valid_strict_request_still_succeeds(): void
    {
        $campaign = $this->campaign();
        $application = $this->application($campaign, 90);

        $this->getJson(
            "/api/v1/campaigns/{$campaign->id()}/applicant-summary?min_score=0&filter[status]=pending&filter[applied_from]=2026-09-01&sort=-appliedAt&page[size]=100&page[number]=1",
            $this->authHeaders(),
        )->assertOk()->assertJsonCount(1, 'data');

        $this->patchJsonApi("/api/v1/applications/{$application->id()}", $this->statusBody($application->id(), 'shortlisted'))
            ->assertOk()
            ->assertJsonPath('data.attributes.status', 'shortlisted');
    }

    /** @return array<string, mixed> */
    private function statusBody(string $id, string $status): array
    {
        return ['data' => ['type' => 'applications', 'id' => $id, 'attributes' => ['status' => $status]]];
    }

    /** @return array<string, string> */
    private function jsonApiServer(): array
    {
        return [
            'HTTP_AUTHORIZATION' => 'Bearer '.self::TOKEN,
            'HTTP_ACCEPT' => 'application/vnd.api+json',
            'CONTENT_TYPE' => 'application/vnd.api+json',
        ];
    }
}
