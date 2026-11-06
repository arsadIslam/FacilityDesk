<?php

use App\Http\Controllers\Web\AssetController;
use App\Http\Controllers\Web\AttachmentController;
use App\Http\Controllers\Web\Auth\EmailVerificationController;
use App\Http\Controllers\Web\Auth\LoginController;
use App\Http\Controllers\Web\Auth\PasswordResetController;
use App\Http\Controllers\Web\CategoryController;
use App\Http\Controllers\Web\ClientController;
use App\Http\Controllers\Web\InvitationController;
use App\Http\Controllers\Web\LocationController;
use App\Http\Controllers\Web\ReportController;
use App\Http\Controllers\Web\SiteController;
use App\Http\Controllers\Web\SiteCoverageController;
use App\Http\Controllers\Web\SlaPolicyController;
use App\Http\Controllers\Web\TenantSignupController;
use App\Http\Controllers\Web\UserController;
use App\Livewire\Dashboard;
use App\Livewire\Reports\Index;
use App\Livewire\WorkOrders;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Central domain (facilitydesk.test)
|--------------------------------------------------------------------------
*/

Route::domain(config('tenancy.domain'))->group(function () {
    Route::view('/', 'welcome')->name('home');

    Route::get('/signup', [TenantSignupController::class, 'create'])->name('signup.create');
    Route::post('/signup', [TenantSignupController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('signup.store');
    Route::get('/signup/success', [TenantSignupController::class, 'success'])->name('signup.success');
});

/*
|--------------------------------------------------------------------------
| Tenant subdomains ({tenant}.facilitydesk.test)
|--------------------------------------------------------------------------
|
| "tenant" must stay before "auth" in every group below so that the user
| provider lookup is already tenant-scoped.
|
*/

Route::domain('{tenant}.'.config('tenancy.domain'))
    ->middleware(['tenant', 'tenant.active'])
    ->group(function () {
        Route::middleware('guest')->group(function () {
            Route::get('/login', [LoginController::class, 'create'])->name('login');
            Route::post('/login', [LoginController::class, 'store'])->name('login.store');

            Route::get('/forgot-password', [PasswordResetController::class, 'create'])->name('password.request');
            Route::post('/forgot-password', [PasswordResetController::class, 'store'])
                ->middleware('throttle:6,1')
                ->name('password.email');
            Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
            Route::post('/reset-password', [PasswordResetController::class, 'update'])->name('password.update');

            Route::middleware('signed')->group(function () {
                Route::get('/invitations/{user}', [InvitationController::class, 'show'])->name('invitations.show');
                Route::post('/invitations/{user}', [InvitationController::class, 'store'])->name('invitations.store');
            });
        });

        Route::middleware(['auth', 'active'])->group(function () {
            Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

            Route::get('/verify-email', [EmailVerificationController::class, 'notice'])->name('verification.notice');
            Route::get('/verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])
                ->middleware(['signed', 'throttle:6,1'])
                ->name('verification.verify');
            Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])
                ->middleware('throttle:6,1')
                ->name('verification.send');

            Route::middleware('verified')->group(function () {
                Route::get('/', Dashboard::class)->name('dashboard');

                Route::get('/work-orders', WorkOrders\Index::class)->name('work-orders.index');
                Route::get('/work-orders/create', WorkOrders\Create::class)->name('work-orders.create');
                Route::get('/work-orders/{workOrder}', WorkOrders\Show::class)->name('work-orders.show');

                Route::get('/attachments/{attachment}', [AttachmentController::class, 'show'])
                    ->middleware('signed')
                    ->name('attachments.show');

                Route::resource('users', UserController::class)->except(['show', 'destroy']);
                Route::post('/users/{user}/invitation', [UserController::class, 'resendInvitation'])->name('users.invitation');
                Route::patch('/users/{user}/activate', [UserController::class, 'activate'])->name('users.activate');
                Route::patch('/users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');

                Route::resource('clients', ClientController::class)->except('show');

                Route::resource('sites', SiteController::class);
                Route::put('/sites/{site}/coverage', [SiteCoverageController::class, 'update'])->name('sites.coverage');
                Route::resource('sites.locations', LocationController::class)
                    ->only(['store', 'edit', 'update', 'destroy'])
                    ->scoped();

                Route::resource('assets', AssetController::class);
                Route::get('/assets/{asset}/label', [AssetController::class, 'label'])->name('assets.label');
                // Target of the printed QR code. Bound by token through the tenant scope,
                // so another tenant's token is a 404 on this subdomain.
                Route::get('/a/{asset:qr_token}', [AssetController::class, 'scan'])->name('assets.scan');

                Route::resource('categories', CategoryController::class)->except(['show', 'destroy']);

                Route::get('/reports', Index::class)->name('reports.index');
                Route::get('/reports/{report}/{format}', [ReportController::class, 'download'])
                    ->whereIn('format', ['pdf', 'xlsx'])
                    ->middleware('signed')
                    ->name('reports.download');

                Route::get('/audit', App\Livewire\Audit\Index::class)->name('audit.index');

                Route::get('/sla-policies', [SlaPolicyController::class, 'index'])->name('sla-policies.index');
                Route::put('/sla-policies/{slaPolicy}', [SlaPolicyController::class, 'update'])->name('sla-policies.update');
            });
        });
    });
