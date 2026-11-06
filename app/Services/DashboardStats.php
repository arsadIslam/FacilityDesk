<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\WorkOrderStatus;
use App\Models\Tenant;
use App\Models\WorkOrder;
use Carbon\CarbonImmutable;

/**
 * Headline numbers for the tenant in context. "This month" is the tenant's
 * local calendar month. (Phase 7 adds caching and the per-site breakdowns.)
 */
final class DashboardStats
{
    /**
     * @return array{open: int, unassigned: int, in_progress: int, on_hold: int, breached_open: int, resolved_this_month: int, sla_compliance_percent: float|null}
     */
    public function summary(Tenant $tenant, CarbonImmutable $now): array
    {
        $monthStart = $now->setTimezone($tenant->timezone)->startOfMonth()->utc();

        $byStatus = WorkOrder::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($total) => (int) $total);

        $resolvedThisMonth = WorkOrder::query()->where('resolved_at', '>=', $monthStart);
        $resolved = (clone $resolvedThisMonth)->count();
        $onTime = (clone $resolvedThisMonth)->where('is_resolution_breached', false)->count();

        return [
            'open' => $byStatus->except(WorkOrderStatus::finishedValues())->sum(),
            'unassigned' => $byStatus->get(WorkOrderStatus::Open->value, 0),
            'in_progress' => $byStatus->get(WorkOrderStatus::InProgress->value, 0),
            'on_hold' => $byStatus->get(WorkOrderStatus::OnHold->value, 0),
            'breached_open' => WorkOrder::query()->whereNotIn('status', WorkOrderStatus::finishedValues())->breached()->count(),
            'resolved_this_month' => $resolved,
            // Share of this month's resolutions that met the resolution target; null when there are none.
            'sla_compliance_percent' => $resolved === 0 ? null : round($onTime / $resolved * 100, 1),
        ];
    }
}
