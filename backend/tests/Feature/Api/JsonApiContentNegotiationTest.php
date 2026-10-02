<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsApplications;
use Tests\Support\RefreshDoctrineSchema;
use Tests\TestCase;

final class JsonApiContentNegotiationTest extends TestCase
{
    use RefreshDoctrineSchema;
    use BuildsApplications;

    #[Test]
    public function a_json_api_body_with_unsupported_media_type_parameters_is_415(): void
    {
        $campaign = $this->campaign();
        $application = $this->application($campaign, 90);

        $this->call(
            'PATCH',
            "/api/v1/applications/{$application->id()}",
            server: $this->server([
                'CONTENT_TYPE' => 'application/vnd.api+json; version=2',
                'HTTP_ACCEPT' => 'application/vnd.api+json',
            ]),
            content: json_encode(['data' => ['type' => 'applications', 'id' => $application->id(), 'attributes' => ['status' => 'rejected']]]),
        )
            ->assertStatus(415)
            ->assertJsonPath('errors.0.code', 'unsupported_media_type');
    }

    #[Test]
    public function a_json_api_content_type_is_accepted_for_updates(): void
    {
        $campaign = $this->campaign();
        $application = $this->application($campaign, 90);

        $this->call(
            'PATCH',
            "/api/v1/applications/{$application->id()}",
            server: $this->server([
                'CONTENT_TYPE' => 'application/vnd.api+json',
                'HTTP_ACCEPT' => 'application/vnd.api+json',
            ]),
            content: json_encode(['data' => ['type' => 'applications', 'id' => $application->id(), 'attributes' => ['status' => 'rejected']]]),
        )
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.api+json')
            ->assertJsonPath('data.attributes.status', 'rejected');
    }

    #[Test]
    public function an_accept_header_with_only_parameterised_json_api_types_is_406(): void
    {
        $campaign = $this->campaign();

        $this->get($this->summaryUrl($campaign->id()), [
            'Authorization' => 'Bearer '.self::TOKEN,
            'Accept' => 'application/vnd.api+json; charset=utf-8',
        ])
            ->assertStatus(406)
            ->assertJsonPath('errors.0.code', 'not_acceptable');
    }

    /** @param array<string, string> $extra */
    private function server(array $extra): array
    {
        return ['HTTP_AUTHORIZATION' => 'Bearer '.self::TOKEN] + $extra;
    }
}
