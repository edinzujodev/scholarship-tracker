<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsApplications;
use Tests\Support\RefreshDoctrineSchema;
use Tests\TestCase;

final class CampaignShowTest extends TestCase
{
    use RefreshDoctrineSchema;
    use BuildsApplications;

    #[Test]
    public function it_returns_the_partners_own_campaign(): void
    {
        $campaign = $this->campaign(name: 'Scholars');

        $this->getJson("/api/v1/campaigns/{$campaign->id()}", $this->authHeaders())
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.api+json')
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('data.type', 'campaigns')
            ->assertJsonPath('data.id', $campaign->id())
            ->assertJsonPath('data.attributes.name', 'Scholars')
            ->assertJsonStructure(['data' => ['links' => ['self', 'applicantSummary']]]);
    }

    #[Test]
    public function another_partners_campaign_and_an_unknown_one_are_the_same_404(): void
    {
        $foreign = $this->campaign(partnerId: 'partner-other');

        $this->getJson("/api/v1/campaigns/{$foreign->id()}", $this->authHeaders())
            ->assertNotFound()
            ->assertJsonPath('errors.0.code', 'not_found');
        $this->getJson('/api/v1/campaigns/'.Str::uuid(), $this->authHeaders())
            ->assertNotFound()
            ->assertJsonPath('errors.0.code', 'not_found');
    }

    #[Test]
    public function a_malformed_id_is_422_and_a_missing_token_is_401(): void
    {
        $campaign = $this->campaign();

        $this->getJson('/api/v1/campaigns/not-a-uuid', $this->authHeaders())
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.source.parameter', 'campaignId');
        $this->getJson("/api/v1/campaigns/{$campaign->id()}")
            ->assertUnauthorized();
    }
}
