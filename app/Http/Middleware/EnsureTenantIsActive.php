<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureTenantIsActive
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->context->tenant();

        abort_if($tenant === null, 404);
        abort_unless($tenant->isActive(), 403, 'This workspace has been suspended. Please contact support.');

        return $next($request);
    }
}
