<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the current tenant and stores it in TenantContext.
 *
 * Web: from the "{tenant}" subdomain route parameter.
 * Otherwise: from the authenticated user (no subdomain, e.g. the API).
 *
 * Registered in the middleware priority list ahead of authentication and
 * SubstituteBindings (bootstrap/app.php), so the user lookup and every
 * route-model binding are already tenant-scoped.
 */
final class IdentifyTenant
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->resolve($request);

        abort_if($tenant === null, 404);

        $this->context->set($tenant);

        // Controllers never receive the subdomain, and route() keeps generating
        // URLs on the current tenant's subdomain without passing it explicitly.
        URL::defaults(['tenant' => $tenant->slug]);
        $request->route()?->forgetParameter('tenant');

        return $next($request);
    }

    private function resolve(Request $request): ?Tenant
    {
        $slug = $request->route('tenant');

        if (is_string($slug)) {
            return Tenant::query()->where('slug', $slug)->first();
        }

        $user = $request->user();

        if ($user instanceof User && $user->tenant_id !== null) {
            return Tenant::query()->find($user->tenant_id);
        }

        return null;
    }
}
