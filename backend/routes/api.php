<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\ApplicantSummaryController;
use App\Http\Controllers\Api\V1\ApplicationStatusController;
use App\Http\Controllers\Api\V1\CampaignIndexController;
use App\Http\Controllers\Api\V1\CampaignShowController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware(['jsonapi', 'partner.auth', 'throttle:api'])
    ->group(function (): void {
        Route::get('campaigns', CampaignIndexController::class)
            ->name('api.v1.campaigns.index');

        Route::get('campaigns/{campaignId}', CampaignShowController::class)
            ->name('api.v1.campaigns.show');

        Route::get('campaigns/{campaignId}/applicant-summary', ApplicantSummaryController::class)
            ->name('api.v1.campaigns.applicant-summary');

        // Status belongs to an application (one applicant in one campaign), so
        // it is updated on the application resource itself.
        Route::patch('applications/{applicationId}', ApplicationStatusController::class)
            ->name('api.v1.applications.update');
    });
