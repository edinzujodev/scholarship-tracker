<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Http\JsonApi\JsonApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Controllers answer with JSON:API documents. Partner-scoped documents must
 * never be stored by a shared cache (CDN, proxy), so that rule lives here
 * instead of being repeated in every controller.
 */
trait RespondsWithJsonApi
{
    /** @param array<string, mixed> $document */
    protected function privateDocument(array $document, int $status = 200): JsonResponse
    {
        return JsonApiResponse::make($document, $status, ['Cache-Control' => 'private, no-store']);
    }
}
