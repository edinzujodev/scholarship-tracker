<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Logging\QueryStats;
use App\Support\RequestId;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Global, first: gives the request its correlation id, attaches it to every
 * log line (all channels) and returns it in the X-Request-Id response header.
 */
final class AssignRequestId
{
    public function __construct(private readonly QueryStats $queries)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $id = RequestId::resolve($request);
        $request->attributes->set(RequestId::ATTRIBUTE, $id);
        $this->queries->reset();
        Log::shareContext(['request_id' => $id]);

        $response = $next($request);
        $response->headers->set(RequestId::HEADER, $id);

        return $response;
    }
}
