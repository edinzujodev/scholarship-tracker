<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * Mock partner authentication: resolves "Authorization: Bearer <token>" to a
 * partner id and stores it on the request. In production this is the OAuth2
 * guard; controllers only ever read the "partner_id" attribute.
 */
final class AuthenticatePartner
{
    public const ATTRIBUTE = 'partner_id';

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $partnerId = $token !== null ? $this->resolvePartner($token) : null;

        if ($partnerId === null) {
            throw new UnauthorizedHttpException('Bearer', 'A valid partner bearer token is required.');
        }

        $request->attributes->set(self::ATTRIBUTE, $partnerId);

        return $next($request);
    }

    /**
     * Fails fast on a misconfigured .env instead of silently locking a partner
     * out: every partner needs a non-empty token, and no two may share one.
     *
     * @param array<array-key, mixed> $partners partner id => token
     */
    public static function assertValidConfiguration(array $partners): void
    {
        foreach ($partners as $partnerId => $token) {
            if (! is_string($token) || trim($token) === '') {
                throw new RuntimeException("Partner \"{$partnerId}\" has no API token. Set it in .env or remove the empty line to use the default.");
            }
        }
        if (count(array_unique($partners)) !== count($partners)) {
            throw new RuntimeException('Two partners share the same API token. Give each partner its own token in .env.');
        }
    }

    private function resolvePartner(string $token): ?string
    {
        /** @var array<string, string> $partners partner id => token */
        $partners = config('partners.partners', []);

        // Compare every candidate in constant time rather than using an array
        // key lookup, so response timing does not leak token prefixes.
        $match = null;
        foreach ($partners as $partnerId => $candidate) {
            if (hash_equals((string) $candidate, $token)) {
                $match = (string) $partnerId;
            }
        }

        return $match;
    }
}
