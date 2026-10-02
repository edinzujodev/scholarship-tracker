<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ApplicationSort;
use App\Enums\ApplicationStatus;
use App\Http\Requests\Concerns\RejectsUnknownQueryParameters;
use App\Http\Requests\Concerns\ResolvesPartner;
use App\Http\Requests\Concerns\ValidatesJsonApiPagination;
use App\Services\Dto\ApplicationCriteria;
use App\Services\Dto\ApplicantSummaryQuery;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Query parameters (JSON:API 1.1 conventions):
 *   min_score=80                                  implementation-specific (contains "_", as the spec requires)
 *   filter[status]=shortlisted
 *   filter[applied_from]=2026-09-01               inclusive, UTC day
 *   filter[applied_to]=2026-09-30                 inclusive, UTC day
 *   sort=-aiScore | aiScore | -appliedAt | appliedAt | name | -name
 *   page[size]=10&page[number]=1
 */
final class ApplicantSummaryRequest extends FormRequest
{
    use RejectsUnknownQueryParameters;
    use ResolvesPartner;
    use ValidatesJsonApiPagination;

    public const DEFAULT_PAGE_SIZE = 10;
    public const MAX_PAGE_SIZE = 100;

    protected function defaultPageSize(): int
    {
        return self::DEFAULT_PAGE_SIZE;
    }

    protected function maxPageSize(): int
    {
        return self::MAX_PAGE_SIZE;
    }

    /** Route parameters are validated alongside the query string. */
    public function validationData(): array
    {
        return array_merge($this->query(), [
            'campaignId' => $this->route('campaignId'),
        ]);
    }

    public function rules(): array
    {
        return [
            ...$this->paginationRules(),
            'campaignId' => ['required', 'uuid'],
            'min_score' => ['sometimes', 'string', self::CANONICAL_INTEGER, 'integer', 'between:0,100'],
            'filter' => ['sometimes', 'array:status,applied_from,applied_to'],
            'filter.status' => ['sometimes', 'string', 'filled', Rule::enum(ApplicationStatus::class)],
            'filter.applied_from' => ['sometimes', 'string', 'date_format:Y-m-d'],
            'filter.applied_to' => ['sometimes', 'string', 'date_format:Y-m-d', 'after_or_equal:filter.applied_from'],
            // An unsupported sort is a 400 per JSON:API (see JsonApiErrorRenderer).
            'sort' => ['sometimes', 'string', Rule::in(ApplicationSort::values())],
        ];
    }

    protected function allowedQueryParameters(): array
    {
        return ['min_score', 'filter', 'sort', 'page'];
    }

    public function messages(): array
    {
        return [
            ...$this->paginationMessages(),
            'campaignId.uuid' => 'The campaign id must be a valid UUID.',
            'min_score.regex' => 'min_score must be a whole number without sign, spaces or leading zeros.',
            'filter.array' => 'Only filter[status], filter[applied_from] and filter[applied_to] are supported.',
            'filter.applied_to.after_or_equal' => 'filter[applied_to] must be on or after filter[applied_from].',
            'sort.in' => 'Unsupported sort. Allowed: '.implode(', ', ApplicationSort::values()).'.',
        ];
    }

    public function toQuery(): ApplicantSummaryQuery
    {
        $validated = $this->validated();
        $filter = $validated['filter'] ?? [];
        $minScore = $validated['min_score'] ?? null; // a validated canonical integer string, or absent

        return new ApplicantSummaryQuery(
            campaignId: strtolower((string) $validated['campaignId']),
            partnerId: $this->partnerId(),
            criteria: new ApplicationCriteria(
                minScore: $minScore === null ? null : (int) $minScore,
                status: isset($filter['status']) ? ApplicationStatus::from($filter['status']) : null,
                appliedFrom: self::utcDay($filter['applied_from'] ?? null),
                appliedTo: self::utcDay($filter['applied_to'] ?? null),
            ),
            pageSize: $this->pageSize(),
            pageNumber: $this->pageNumber(),
            sort: ApplicationSort::from($validated['sort'] ?? ApplicationSort::DEFAULT->value),
        );
    }

    private static function utcDay(?string $date): ?DateTimeImmutable
    {
        if ($date === null) {
            return null;
        }

        return DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('UTC')) ?: null;
    }
}
