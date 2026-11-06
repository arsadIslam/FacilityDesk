<?php

use App\Http\Controllers\Api\V1;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| REST API v1
|--------------------------------------------------------------------------
|
| Served on the central domain (facilitydesk.test/api/v1); the tenant comes
| from the token's user, never from the URL or the request body.
|
| "tenant:sanctum" resolves the bearer token itself because IdentifyTenant
| is prioritised ahead of authentication (bootstrap/app.php), so route-model
| bindings below are already tenant-scoped: another tenant's id is a 404.
|
*/

Route::domain(config('tenancy.domain'))
    ->prefix('v1')
    ->name('api.v1.')
    ->group(function () {
        Route::post('/auth/login', [V1\AuthController::class, 'login'])
            ->middleware('throttle:api-login')
            ->name('auth.login');

        Route::middleware(['tenant:sanctum', 'tenant.active', 'auth:sanctum', 'active', 'verified', 'throttle:api'])->group(function () {
            Route::post('/auth/logout', [V1\AuthController::class, 'logout'])->name('auth.logout');
            Route::get('/me', [V1\AuthController::class, 'me'])->name('me');

            Route::get('/work-orders', [V1\WorkOrderController::class, 'index'])->name('work-orders.index');
            Route::post('/work-orders', [V1\WorkOrderController::class, 'store'])->name('work-orders.store');
            Route::get('/work-orders/{workOrder}', [V1\WorkOrderController::class, 'show'])->name('work-orders.show');
            Route::patch('/work-orders/{workOrder}/assign', [V1\WorkOrderController::class, 'assign'])->name('work-orders.assign');
            Route::patch('/work-orders/{workOrder}/status', [V1\WorkOrderController::class, 'status'])->name('work-orders.status');
            Route::post('/work-orders/{workOrder}/comments', [V1\WorkOrderCommentController::class, 'store'])->name('work-orders.comments.store');
            Route::post('/work-orders/{workOrder}/media', [V1\WorkOrderMediaController::class, 'store'])->name('work-orders.media.store');

            Route::get('/attachments/{attachment}', [V1\AttachmentController::class, 'show'])->name('attachments.show');

            Route::get('/technician/jobs', [V1\TechnicianJobController::class, 'index'])->name('technician.jobs');

            Route::get('/sites', [V1\SiteController::class, 'index'])->name('sites.index');
            Route::get('/sites/{site}/locations', [V1\SiteController::class, 'locations'])->name('sites.locations');

            Route::get('/assets/qr/{asset:qr_token}', [V1\AssetController::class, 'qr'])->name('assets.qr');

            Route::get('/dashboard/summary', [V1\DashboardController::class, 'summary'])->name('dashboard.summary');
        });
    });
