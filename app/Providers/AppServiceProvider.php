<?php

declare(strict_types=1);

namespace App\Providers;

use App\Auth\TenantPasswordBrokerManager;
use App\Models\Asset;
use App\Models\Attachment;
use App\Models\Category;
use App\Models\Client;
use App\Models\Location;
use App\Models\Report;
use App\Models\Site;
use App\Models\SlaPolicy;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderComment;
use App\Notifications\Channels\TenantDatabaseChannel;
use App\Services\TenantContext;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Livewire;
use LogicException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Scoped (not singleton): flushed between requests and queued jobs, so a
        // tenant can never leak from one unit of work into the next.
        $this->app->scoped(TenantContext::class);

        // Reset tokens keyed by (tenant_id, email); see TenantDatabaseTokenRepository.
        $this->app->extend('auth.password', fn ($manager, Application $app) => new TenantPasswordBrokerManager($app));

        // Database notifications carry tenant_id; see TenantDatabaseChannel.
        $this->app->bind(DatabaseChannel::class, TenantDatabaseChannel::class);

        // Must run before Livewire boots: once a "livewire.update" route exists,
        // Livewire skips its default one, which has no domain and no tenant
        // middleware and would hydrate components with no tenant in context.
        $this->app->booting(fn () => Livewire::setUpdateRoute(
            fn (array $handle) => Route::domain('{tenant}.'.config('tenancy.domain'))
                ->middleware(['web', 'tenant', 'tenant.active', 'auth', 'active', 'verified'])
                ->post('/livewire/update', $handle)
                ->name('tenant.livewire.update'),
        ));
    }

    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

        Relation::enforceMorphMap([
            'tenant' => Tenant::class,
            'user' => User::class,
            'client' => Client::class,
            'site' => Site::class,
            'location' => Location::class,
            'category' => Category::class,
            'sla_policy' => SlaPolicy::class,
            'work_order' => WorkOrder::class,
            'work_order_comment' => WorkOrderComment::class,
            'attachment' => Attachment::class,
            'asset' => Asset::class,
            'report' => Report::class,
        ]);

        $this->configureAuthLinks();
        $this->configureRateLimiting();
    }

    /**
     * API: 60 requests a minute per token (per IP before login); login: 5 a
     * minute per IP to slow down credential stuffing.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            $user = $request->user();
            $token = $user instanceof User ? $user->currentAccessToken() : null;

            return Limit::perMinute(60)->by($token instanceof PersonalAccessToken ? 'token:'.$token->getKey() : 'ip:'.$request->ip());
        });

        RateLimiter::for('api-login', fn (Request $request) => Limit::perMinute(5)->by('login:'.$request->ip()));
    }

    /**
     * Auth emails may be sent from outside a tenant request (e.g. signup on the
     * central domain), so their links name the user's tenant subdomain explicitly.
     */
    private function configureAuthLinks(): void
    {
        VerifyEmail::createUrlUsing(fn (User $user): string => URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes((int) config('auth.verification.expire', 60)),
            [
                'tenant' => $this->tenantSlug($user),
                'id' => $user->getKey(),
                'hash' => sha1($user->getEmailForVerification()),
            ],
        ));

        ResetPassword::createUrlUsing(fn (User $user, string $token): string => route('password.reset', [
            'tenant' => $this->tenantSlug($user),
            'token' => $token,
            'email' => $user->getEmailForPasswordReset(),
        ]));
    }

    private function tenantSlug(User $user): string
    {
        // Super Admins have no tenant (tenant_id NULL) and never use these links.
        $tenant = $user->loadMissing('tenant')->getRelation('tenant');

        if (! $tenant instanceof Tenant) {
            throw new LogicException('Tenant auth links require a tenant user.');
        }

        return $tenant->slug;
    }
}
