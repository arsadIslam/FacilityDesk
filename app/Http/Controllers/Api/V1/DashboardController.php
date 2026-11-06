<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DashboardStats;
use App\Services\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response as ScribeResponse;

#[Group('Dashboard')]
#[Authenticated]
final class DashboardController extends Controller
{
    /**
     * Summary
     *
     * Supervisors and admins. "This month" is the workspace's local month.
     */
    #[ScribeResponse(['data' => ['open' => 42, 'unassigned' => 6, 'in_progress' => 18, 'on_hold' => 3, 'breached_open' => 4, 'resolved_this_month' => 120, 'sla_compliance_percent' => 93.3]])]
    public function summary(Request $request, TenantContext $context, DashboardStats $stats): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->hasPermission(Permission::ViewAllWorkOrders), 403);

        /** @var Tenant $tenant */
        $tenant = $context->tenant();

        return response()->json(['data' => $stats->summary($tenant, CarbonImmutable::now())]);
    }
}
