<?php

declare(strict_types=1);

namespace App\Jobs\Middleware;

use App\Models\Tenant;
use App\Services\TenantContext;
use Closure;

/**
 * Restores the tenant a queued job or listener was dispatched for.
 *
 * Workers serve every tenant, so without this the global tenant scope and
 * Spatie's team id would be empty inside the job. Jobs carry only the
 * tenant id; the tenant is re-read so a suspended or deleted tenant is seen.
 */
final class SetTenantContext
{
    public function __construct(public readonly int $tenantId) {}

    public function handle(object $job, Closure $next): mixed
    {
        $tenant = Tenant::query()->find($this->tenantId);

        // The tenant was deleted after dispatch: there is nothing left to act on.
        if ($tenant === null) {
            if (method_exists($job, 'delete')) {
                $job->delete();
            }

            return null;
        }

        return app(TenantContext::class)->run($tenant, fn () => $next($job));
    }
}
