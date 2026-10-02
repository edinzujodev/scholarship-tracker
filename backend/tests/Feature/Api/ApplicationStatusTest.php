<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Entities\Application;
use App\Enums\ApplicationStatus;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsApplications;
use Tests\Support\RefreshDoctrineSchema;
use Tests\TestCase;

final class ApplicationStatusTest extends TestCase
{
    use RefreshDoctrineSchema;
    use BuildsApplications;

    #[Test]
    public function a_partner_can_shortlist_an_application(): void
    {
        $application = $this->application($this->campaign(), 90, name: 'Ada Lovelace');

        $this->patchJsonApi($this->url($application->id()), $this->body($application->id(), 'shortlisted'))
            ->assertOk()
            ->assertJsonPath('data.type', 'applications')
            ->assertJsonPath('data.id', $application->id())
            ->assertJsonPath('data.attributes.status', 'shortlisted')
            ->assertJsonPath('data.relationships.applicant.data.id', $application->applicant()->id())
            ->assertJsonPath('included.0.attributes', ['firstName' => 'Ada', 'middleName' => null, 'lastName' => 'Lovelace']);

        $this->assertSame(ApplicationStatus::Shortlisted, $this->reload($application)->status());
    }

    #[Test]
    public function status_is_per_application_so_the_same_person_can_differ_between_campaigns(): void
    {
        $person = $this->person('Grace Hopper');
        $scholars = $this->application($this->campaign(name: 'Scholars'), 92, applicant: $person);
        $fellowship = $this->application($this->campaign(name: 'Fellowship'), 64, applicant: $person);

        $this->patchJsonApi($this->url($scholars->id()), $this->body($scholars->id(), 'shortlisted'))->assertOk();
        $this->patchJsonApi($this->url($fellowship->id()), $this->body($fellowship->id(), 'rejected'))->assertOk();

        $this->assertSame(ApplicationStatus::Shortlisted, $this->reload($scholars)->status());
        $this->assertSame(ApplicationStatus::Rejected, $this->reload($fellowship)->status());
        $this->assertSame($this->reload($scholars)->applicant()->id(), $this->reload($fellowship)->applicant()->id());
    }

    #[Test]
    public function an_unknown_status_is_rejected_with_a_json_pointer(): void
    {
        $application = $this->application($this->campaign(), 90);

        $this->patchJsonApi($this->url($application->id()), $this->body($application->id(), 'hired'))
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.source.pointer', '/data/attributes/status');
    }

    #[Test]
    public function a_malformed_application_id_is_422(): void
    {
        $this->patchJsonApi($this->url('not-a-uuid'), $this->body('not-a-uuid', 'rejected'))
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.source.parameter', 'applicationId');
    }

    #[Test]
    public function a_mismatched_resource_id_or_type_is_a_conflict(): void
    {
        $application = $this->application($this->campaign(), 90);

        $this->patchJsonApi($this->url($application->id()), $this->body((string) Str::uuid(), 'rejected'))
            ->assertStatus(409)
            ->assertJsonPath('errors.0.code', 'conflict');

        $wrongType = ['data' => ['type' => 'applicants', 'id' => $application->id(), 'attributes' => ['status' => 'rejected']]];
        $this->patchJsonApi($this->url($application->id()), $wrongType)->assertStatus(409);
    }

    #[Test]
    public function a_partner_cannot_update_an_application_in_another_partners_campaign(): void
    {
        $application = $this->application($this->campaign(), 90);

        $this->patchJsonApi($this->url($application->id()), $this->body($application->id(), 'rejected'), self::OTHER_TOKEN)
            ->assertNotFound()
            ->assertJsonPath('errors.0.code', 'not_found');

        $this->assertSame(ApplicationStatus::Pending, $this->reload($application)->status());
    }

    #[Test]
    public function an_unknown_application_is_not_found(): void
    {
        $id = (string) Str::uuid();

        $this->patchJsonApi($this->url($id), $this->body($id, 'rejected'))->assertNotFound();
    }

    private function url(string $applicationId): string
    {
        return "/api/v1/applications/{$applicationId}";
    }

    /** @return array<string, mixed> */
    private function body(string $id, string $status): array
    {
        return ['data' => ['type' => 'applications', 'id' => $id, 'attributes' => ['status' => $status]]];
    }

    private function reload(Application $application): Application
    {
        $this->em->clear();

        /** @var Application */
        return $this->em->find(Application::class, $application->id());
    }
}
