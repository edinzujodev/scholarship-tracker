<?php

declare(strict_types=1);

namespace App\Http\JsonApi;

use App\Support\RequestId;
use Illuminate\Http\JsonResponse;

final class JsonApiResponse
{
    public const MEDIA_TYPE = 'application/vnd.api+json';

    /** @param array<string, mixed> $document */
    public static function make(array $document, int $status = 200, array $headers = []): JsonResponse
    {
        // Error documents carry the request id so a partner can quote it to
        // support, and it matches every log line written for this request.
        $requestId = RequestId::current();
        if (isset($document['errors']) && $requestId !== null) {
            $document['meta'] = ['requestId' => $requestId] + ($document['meta'] ?? []);
        }

        return new JsonResponse(
            data: ['jsonapi' => ['version' => '1.1']] + $document,
            status: $status,
            headers: ['Content-Type' => self::MEDIA_TYPE] + $headers,
            options: JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION,
        );
    }
}
