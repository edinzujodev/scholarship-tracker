<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ApplicationStatus;
use App\Http\Requests\Concerns\RejectsUnknownMembers;
use App\Http\Requests\Concerns\RejectsUnknownQueryParameters;
use App\Http\Requests\Concerns\ResolvesPartner;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * JSON:API update document for PATCH /api/v1/applications/{applicationId}:
 * { "data": { "type": "applications", "id": "<uuid>", "attributes": { "status": "shortlisted" } } }
 *
 * Strict: only `status` may be changed; any other member (a read-only
 * attribute, relationships, unknown top-level members) is a 422 with a JSON
 * pointer, and nothing is written. A type/id that disagrees with the URL is a
 * 409 (see ApplicationStatusController).
 */
final class UpdateApplicationStatusRequest extends FormRequest
{
    use RejectsUnknownMembers;
    use RejectsUnknownQueryParameters;
    use ResolvesPartner;

    public function validationData(): array
    {
        return array_merge($this->json()->all(), [
            'applicationId' => $this->route('applicationId'),
        ]);
    }

    public function rules(): array
    {
        return [
            'applicationId' => ['required', 'uuid'],
            'data' => ['required', 'array'],
            'data.type' => ['required', 'string'],
            'data.id' => ['required', 'string', 'uuid'],
            'data.attributes' => ['required', 'array'],
            'data.attributes.status' => ['required', 'string', Rule::enum(ApplicationStatus::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'applicationId.uuid' => 'The application id must be a valid UUID.',
            'data.id.uuid' => 'data.id must be a valid UUID.',
        ];
    }

    /** Writes take no query parameters. */
    protected function allowedQueryParameters(): array
    {
        return [];
    }

    protected function allowedMembers(): array
    {
        return [
            '' => ['data', 'meta', 'jsonapi'],
            'data' => ['type', 'id', 'attributes', 'meta'],
            'data.attributes' => ['status'],
        ];
    }

    public function applicationId(): string
    {
        return strtolower((string) $this->validated('applicationId'));
    }

    public function resourceType(): string
    {
        return (string) $this->validated('data.type');
    }

    public function resourceId(): string
    {
        return strtolower((string) $this->validated('data.id'));
    }

    public function status(): ApplicationStatus
    {
        return ApplicationStatus::from((string) $this->validated('data.attributes.status'));
    }
}
