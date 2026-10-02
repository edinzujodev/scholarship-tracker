<?php

declare(strict_types=1);

namespace App\Http\JsonApi;

use App\Exceptions\ApplicationNotFound;
use App\Exceptions\CampaignNotFound;
use App\Exceptions\JsonApiBadRequest;
use App\Exceptions\ResourceConflict;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Renders every exception under /api/* as a JSON:API error document:
 * { "errors": [ { "status", "code", "title", "detail", "source" } ] }
 */
final class JsonApiErrorRenderer
{
    /** Request fields that live in the URL rather than the body. */
    private const URL_PARAMETERS = [
        'campaignId', 'applicationId', 'min_score', 'sort',
        'page', 'page.size', 'page.number',
        'filter', 'filter.status', 'filter.applied_from', 'filter.applied_to',
    ];

    /**
     * JSON:API: "If the server does not support sorting as specified in the
     * query parameter sort, it MUST return 400 Bad Request."
     */
    private const BAD_REQUEST_FIELDS = ['sort'];

    public function __invoke(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null;
        }

        return match (true) {
            $e instanceof JsonApiBadRequest => $this->badRequest($e),
            $e instanceof ValidationException => $this->validation($e),
            $e instanceof CampaignNotFound, $e instanceof ApplicationNotFound => $this->single(404, 'not_found', 'Resource not found', $e->getMessage()),
            $e instanceof ResourceConflict => $this->single(409, 'conflict', 'Conflict', $e->getMessage()),
            $e instanceof HttpExceptionInterface => $this->http($e),
            default => $this->single(
                500,
                'server_error',
                'Internal server error',
                config('app.debug') ? $e->getMessage() : 'An unexpected error occurred.',
            ),
        };
    }

    private function badRequest(JsonApiBadRequest $e): JsonResponse
    {
        return JsonApiResponse::make([
            'errors' => array_map(static fn (array $error): array => [
                'status' => '400',
                'title' => 'Bad request',
                ...$error,
            ], $e->errors),
        ], 400);
    }

    private function validation(ValidationException $e): JsonResponse
    {
        $errors = [];
        $badRequest = false;
        foreach ($e->errors() as $field => $messages) {
            $isBadRequest = in_array($field, self::BAD_REQUEST_FIELDS, true);
            $badRequest = $badRequest || $isBadRequest;
            foreach ($messages as $message) {
                $errors[] = [
                    'status' => $isBadRequest ? '400' : '422',
                    'code' => $isBadRequest ? 'unsupported_sort' : 'validation_failed',
                    'title' => $isBadRequest ? 'Bad request' : 'Invalid attribute',
                    'detail' => $message,
                    'source' => $this->source((string) $field),
                ];
            }
        }

        // With mixed errors, JSON:API asks for the most generally applicable code.
        return JsonApiResponse::make(['errors' => $errors], $badRequest ? 400 : 422);
    }

    private function http(HttpExceptionInterface $e): JsonResponse
    {
        $status = $e->getStatusCode();
        [$code, $title] = match ($status) {
            401 => ['unauthenticated', 'Unauthenticated'],
            403 => ['forbidden', 'Forbidden'],
            404 => ['not_found', 'Resource not found'],
            405 => ['method_not_allowed', 'Method not allowed'],
            406 => ['not_acceptable', 'Not acceptable'],
            415 => ['unsupported_media_type', 'Unsupported media type'],
            429 => ['too_many_requests', 'Too many requests'],
            default => ['http_error', 'HTTP error'],
        };

        $detail = $e->getMessage() !== '' ? $e->getMessage() : $title;
        $response = $this->single($status, $code, $title, $detail);
        $response->headers->add($e->getHeaders());

        return $response;
    }

    private function single(int $status, string $code, string $title, string $detail): JsonResponse
    {
        return JsonApiResponse::make([
            'errors' => [[
                'status' => (string) $status,
                'code' => $code,
                'title' => $title,
                'detail' => $detail,
            ]],
        ], $status);
    }

    /**
     * URL inputs map to source.parameter ("page[size]"), body inputs map to a
     * JSON pointer ("/data/attributes/status").
     *
     * @return array<string, string>
     */
    private function source(string $field): array
    {
        if (in_array($field, self::URL_PARAMETERS, true)) {
            $parts = explode('.', $field);
            $name = array_shift($parts).implode('', array_map(static fn ($p) => "[{$p}]", $parts));

            return ['parameter' => $name];
        }

        return ['pointer' => '/'.str_replace('.', '/', $field)];
    }
}
