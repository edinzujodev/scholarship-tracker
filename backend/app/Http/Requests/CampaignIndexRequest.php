<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RejectsUnknownQueryParameters;
use App\Http\Requests\Concerns\ResolvesPartner;
use App\Http\Requests\Concerns\ValidatesJsonApiPagination;
use Illuminate\Foundation\Http\FormRequest;

final class CampaignIndexRequest extends FormRequest
{
    use RejectsUnknownQueryParameters;
    use ResolvesPartner;
    use ValidatesJsonApiPagination;

    public const DEFAULT_PAGE_SIZE = 20;
    public const MAX_PAGE_SIZE = 100;

    protected function defaultPageSize(): int
    {
        return self::DEFAULT_PAGE_SIZE;
    }

    protected function maxPageSize(): int
    {
        return self::MAX_PAGE_SIZE;
    }

    public function validationData(): array
    {
        return $this->query();
    }

    public function rules(): array
    {
        return $this->paginationRules();
    }

    protected function allowedQueryParameters(): array
    {
        return ['page'];
    }

    public function messages(): array
    {
        return $this->paginationMessages();
    }
}
