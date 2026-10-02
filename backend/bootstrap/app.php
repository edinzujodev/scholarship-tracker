<?php

use App\Exceptions\ApplicationNotFound;
use App\Exceptions\CampaignNotFound;
use App\Exceptions\JsonApiBadRequest;
use App\Exceptions\ResourceConflict;
use App\Http\JsonApi\JsonApiErrorRenderer;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\AuthenticatePartner;
use App\Http\Middleware\EnsureJsonApiMediaType;
use App\Http\Middleware\LogApiRequest;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Strict validation: the API validates exactly what the client sent.
        // Silently trimming " 80" to "80" would hide malformed input.
        $middleware->trimStrings(except: [
            static fn (Request $request): bool => $request->is('api/*'),
        ]);

        // Global: correlation id first, then one access-log line per API request.
        $middleware->append([AssignRequestId::class, LogApiRequest::class]);

        $middleware->alias([
            'partner.auth' => AuthenticatePartner::class,
            'jsonapi' => EnsureJsonApiMediaType::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        // Expected client errors (4xx) are recorded by the access log, not
        // reported as application errors with stack traces.
        $exceptions->dontReport([
            CampaignNotFound::class,
            ApplicationNotFound::class,
            ResourceConflict::class,
            JsonApiBadRequest::class,
        ]);
        $exceptions->render(new JsonApiErrorRenderer());
    })->create();
