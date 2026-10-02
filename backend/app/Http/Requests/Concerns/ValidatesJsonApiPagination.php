<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

/**
 * JSON:API page-based pagination: `page[size]` and `page[number]`.
 *
 * The using request implements defaultPageSize() and maxPageSize() (an
 * explicit contract PHP enforces, rather than constants the trait would have
 * to assume exist) and merges paginationRules() / paginationMessages() into
 * its own.
 */
trait ValidatesJsonApiPagination
{
    /** Page size used when the client sends no page[size]. */
    abstract protected function defaultPageSize(): int;

    /** Largest page[size] the endpoint accepts. */
    abstract protected function maxPageSize(): int;

    /**
     * A canonical non-negative integer: digits only, no sign, no spaces, no
     * decimals or exponent, no leading zeros. PHP's integer filter alone would
     * accept "+5" and " 5".
     */
    public const CANONICAL_INTEGER = 'regex:/^(0|[1-9][0-9]*)$/';

    /** @return array<string, list<string>> */
    protected function paginationRules(): array
    {
        return [
            'page' => ['sometimes', 'array:size,number'],
            'page.size' => ['sometimes', 'string', self::CANONICAL_INTEGER, 'integer', 'between:1,'.$this->maxPageSize()],
            'page.number' => ['sometimes', 'string', self::CANONICAL_INTEGER, 'integer', 'min:1', 'max:100000'],
        ];
    }

    /** @return array<string, string> */
    protected function paginationMessages(): array
    {
        return [
            'page.array' => 'Only page[size] and page[number] are supported.',
            'page.size.regex' => 'page[size] must be a whole number without sign, spaces or leading zeros.',
            'page.number.regex' => 'page[number] must be a whole number without sign, spaces or leading zeros.',
        ];
    }

    public function pageSize(): int
    {
        return (int) ($this->validated('page.size') ?? $this->defaultPageSize());
    }

    public function pageNumber(): int
    {
        return (int) ($this->validated('page.number') ?? 1);
    }
}
