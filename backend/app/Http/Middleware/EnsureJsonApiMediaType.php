<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\JsonApiBadRequest;
use App\Http\JsonApi\JsonApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * JSON:API 1.1 content negotiation (https://jsonapi.org/format/#content-negotiation-servers):
 *
 * - 415 Unsupported Media Type when a request body is sent as
 *   application/vnd.api+json with media type parameters other than ext/profile.
 * - 406 Not Acceptable when every application/vnd.api+json entry in Accept
 *   carries unsupported parameters.
 *
 * Strict: request bodies must be application/vnd.api+json (plain
 * application/json is a 415), and must be a well-formed JSON object (400).
 */
final class EnsureJsonApiMediaType
{
    private const ALLOWED_PARAMS = ['ext', 'profile'];

    public function handle(Request $request, Closure $next): Response
    {
        $this->assertContentType($request);
        $this->assertWellFormedBody($request);
        $this->assertAcceptable($request);

        return $next($request);
    }

    private function assertContentType(Request $request): void
    {
        if (! in_array($request->getMethod(), ['POST', 'PATCH', 'PUT'], true)) {
            return;
        }

        $contentType = (string) $request->headers->get('Content-Type', '');
        [$type, $params] = $this->parse($contentType);

        if ($type === JsonApiResponse::MEDIA_TYPE && $this->hasUnsupportedParams($params)) {
            throw new HttpException(415, 'Media type parameters other than "ext" and "profile" are not supported.');
        }

        if ($type !== JsonApiResponse::MEDIA_TYPE) {
            throw new HttpException(415, 'Request bodies must be sent as application/vnd.api+json.');
        }
    }

    /** A write must carry a JSON object: not empty, not malformed, not an array or scalar. */
    private function assertWellFormedBody(Request $request): void
    {
        if (! in_array($request->getMethod(), ['POST', 'PATCH', 'PUT'], true)) {
            return;
        }

        $raw = (string) $request->getContent();
        if (trim($raw) === '') {
            throw JsonApiBadRequest::malformedBody('The request body is empty; a JSON:API document is required.');
        }

        try {
            $decoded = json_decode($raw, false, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw JsonApiBadRequest::malformedBody('The request body is not valid JSON: '.$e->getMessage().'.');
        }

        if (! $decoded instanceof \stdClass) {
            throw JsonApiBadRequest::malformedBody('The request body must be a JSON object (a JSON:API document).');
        }
    }

    private function assertAcceptable(Request $request): void
    {
        $jsonApiEntries = array_values(array_filter(
            array_map(fn (string $entry) => $this->parse($entry), explode(',', (string) $request->headers->get('Accept', ''))),
            static fn (array $entry): bool => $entry[0] === JsonApiResponse::MEDIA_TYPE,
        ));

        if ($jsonApiEntries === []) {
            return;
        }

        foreach ($jsonApiEntries as [, $params]) {
            if (! $this->hasUnsupportedParams($params)) {
                return;
            }
        }

        throw new HttpException(406, 'No acceptable application/vnd.api+json media type in the Accept header.');
    }

    /** @return array{0: string, 1: list<string>} media type and its parameter names */
    private function parse(string $value): array
    {
        $parts = array_map('trim', explode(';', $value));
        $type = strtolower(array_shift($parts) ?? '');
        $names = array_map(
            static fn (string $p): string => strtolower(trim(explode('=', $p, 2)[0])),
            array_filter($parts, static fn (string $p): bool => $p !== ''),
        );

        return [$type, array_values($names)];
    }

    /** @param list<string> $params */
    private function hasUnsupportedParams(array $params): bool
    {
        // "q" is the Accept quality factor, not a media type parameter.
        return array_diff($params, [...self::ALLOWED_PARAMS, 'q']) !== [];
    }
}
