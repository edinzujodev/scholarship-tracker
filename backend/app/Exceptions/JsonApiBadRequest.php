<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * 400 Bad Request with one JSON:API error object per problem, e.g. every
 * unsupported query parameter, or a body that is not a JSON object.
 */
final class JsonApiBadRequest extends RuntimeException
{
    /**
     * @param list<array{code: string, detail: string, source?: array<string, string>}> $errors
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct($errors[0]['detail'] ?? 'Bad request');
    }

    /** @param list<string> $parameters */
    public static function unsupportedParameters(array $parameters): self
    {
        return new self(array_map(static fn (string $name): array => [
            'code' => 'unsupported_parameter',
            'detail' => sprintf('The query parameter "%s" is not supported by this endpoint.', $name),
            'source' => ['parameter' => $name],
        ], $parameters));
    }

    public static function malformedBody(string $detail): self
    {
        return new self([['code' => 'malformed_body', 'detail' => $detail, 'source' => ['pointer' => '']]]);
    }
}
