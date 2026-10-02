<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The correlation id of the current request. The Nuxt server creates it and
 * forwards it as X-Request-Id; Laravel reuses it (or creates one for direct
 * API calls), so the browser, the proxy and the API log the same id.
 */
final class RequestId
{
    public const HEADER = 'X-Request-Id';
    public const ATTRIBUTE = 'request_id';

    /** Accept only short, plain ids from clients, so they are safe to log and echo. */
    private const ACCEPTED = '/^[A-Za-z0-9][A-Za-z0-9-]{7,63}$/';

    public static function resolve(Request $request): string
    {
        $incoming = (string) $request->headers->get(self::HEADER, '');

        return preg_match(self::ACCEPTED, $incoming) === 1 ? $incoming : (string) Str::uuid();
    }

    public static function current(): ?string
    {
        $request = app()->bound('request') ? app('request') : null;
        $id = $request?->attributes->get(self::ATTRIBUTE);

        return is_string($id) ? $id : null;
    }
}
