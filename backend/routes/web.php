<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// API-only service: the root sends people to the partner portal (FRONTEND_URL).
// Health checks use /up; the API lives under /api/v1.
Route::redirect('/', config('app.frontend_url'), 302);
