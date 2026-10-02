<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RejectsUnknownQueryParameters;
use App\Http\Requests\Concerns\ResolvesPartner;
use Illuminate\Foundation\Http\FormRequest;

final class ShowCampaignRequest extends FormRequest
{
    use RejectsUnknownQueryParameters;
    use ResolvesPartner;

    public function validationData(): array
    {
        return ['campaignId' => $this->route('campaignId')];
    }

    public function rules(): array
    {
        return ['campaignId' => ['required', 'uuid']];
    }

    /** This endpoint takes no query parameters at all. */
    protected function allowedQueryParameters(): array
    {
        return [];
    }

    public function messages(): array
    {
        return ['campaignId.uuid' => 'The campaign id must be a valid UUID.'];
    }

    public function campaignId(): string
    {
        return strtolower((string) $this->validated('campaignId'));
    }
}
