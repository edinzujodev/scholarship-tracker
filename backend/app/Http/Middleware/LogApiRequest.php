<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Logging\QueryStats;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * One structured access-log line per API request on the `http` channel, and a
 * `security` line for authentication failures, throttling and lookups of
 * resources the caller may not see. Never logs headers, bodies or query
 * values: only parameter names.
 */
final class LogApiRequest
{
    public function __construct(private readonly QueryStats $queries)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('api/*')) {
            return $next($request);
        }

        $start = hrtime(true);
        $response = $next($request);
        $status = $response->getStatusCode();

        $entry = [
            'method' => $request->getMethod(),
            'route' => $request->route()?->getName(),
            'path' => '/'.ltrim((string) ($request->route()?->uri() ?? $request->path()), '/'),
            'status' => $status,
            'duration_ms' => round((hrtime(true) - $start) / 1e6, 2),
            'partner_id' => $request->attributes->get(AuthenticatePartner::ATTRIBUTE),
            'query_params' => array_keys($request->query->all()),
            'db_queries' => $this->queries->count(),
            'db_ms' => $this->queries->totalMs(),
        ];

        $level = $status >= 500 ? 'error' : ($status >= 400 ? 'notice' : 'info');
        Log::channel('http')->log($level, 'API request', $entry);

        if (in_array($status, [401, 403, 404, 429], true)) {
            Log::channel('security')->warning(match ($status) {
                401 => 'Authentication failed',
                403 => 'Access forbidden',
                404 => 'Resource not found or not owned by caller',
                429 => 'Rate limit exceeded',
            }, ['status' => $status, 'route' => $entry['route'], 'partner_id' => $entry['partner_id'], 'ip' => $request->ip()]);
        }

        return $response;
    }
}
