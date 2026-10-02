<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Exceptions\JsonApiBadRequest;

/**
 * JSON:API: a server MUST answer 400 for query parameters it does not know
 * how to process. The using request lists the top-level parameters it
 * supports in allowedQueryParameters(); anything else (including JSON:API
 * families we do not implement, such as `include` or `fields`) is a 400.
 * Nested keys (filter[...], page[...]) are validated by the request's rules.
 */
trait RejectsUnknownQueryParameters
{
    /** @return list<string> */
    abstract protected function allowedQueryParameters(): array;

    protected function prepareForValidation(): void
    {
        $unknown = array_values(array_diff(
            array_map('strval', array_keys($this->query->all())),
            $this->allowedQueryParameters(),
        ));

        if ($unknown !== []) {
            throw JsonApiBadRequest::unsupportedParameters($unknown);
        }
    }
}
