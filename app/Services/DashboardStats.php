<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\WorkOrderStatus;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Dashboard numbers for the tenant in context, cached per tenant for five
 * minutes and invalidated by any work order event (see BustDashboardCache).
 *
 * Invalidation rotates a per-tenant version that is part of every cache key,
 * so all filter combinations expire at once on any cache store, without
 * needing tag support.
 *
 * Periods are the tenant's local days; "now" figures ignore the period.
 */
final class DashboardStats
{
    public const TTL_SECONDS = 300;

    public const TOP_CATEGORIES = 5;

    public static function bust(int $tenantId): void
    {
        Cache::forever(self::versionKey($tenantId), (string) Str::uuid());
    }

    /**
     * Headline numbers for the current month (API summary).
     *
     * @return array{open: int, unassigned: int, in_progress: int, on_hold: int, breached_open: int, resolved_this_month: int, sla_compliance_percent: float|null}
     */
    public function summary(Tenant $tenant, CarbonImmutable $now): array
    {
        $local = $now->setTimezone($tenant->timezone);
        $stats = $this->forPeriod($tenant, $local->startOfMonth(), $local);

        return [
            'open' => $stats['now']['open'],
            'unassigned' => $stats['now']['by_status'][WorkOrderStatus::Open->value] ?? 0,
            'in_progress' => $stats['now']['by_status'][WorkOrderStatus::InProgress->value] ?? 0,
            'on_hold' => $stats['now']['by_status'][WorkOrderStatus::OnHold->value] ?? 0,
            'breached_open' => $stats['now']['breached'],
            'resolved_this_month' => $stats['period']['resolved'],
            'sla_compliance_percent' => $stats['period']['resolution_compliance'],
        ];
    }

    /**
     * @return array{
     *     now: array{open: int, breached: int, by_status: array<string, int>},
     *     period: array{from: string, to: string, created: int, resolved: int, closed: int, resolution_compliance: float|null, response_compliance: float|null},
     *     technicians: list<array{id: int, name: string, open: int, resolved: int, breached: int}>,
     *     top_categories: list<array{name: string, total: int}>,
     *     generated_at: string
     * }
     */
    public function forPeriod(Tenant $tenant, CarbonImmutable $from, CarbonImmutable $to, ?int $siteId = null): array
    {
        $from = $from->setTimezone($tenant->timezone)->startOfDay();
        $to = $to->setTimezone($tenant->timezone)->endOfDay();

        $key = sprintf(
            'dashboard:%d:%s:%s:%s:%s',
            $tenant->id,
            Cache::rememberForever(self::versionKey($tenant->id), fn () => (string) Str::uuid()),
            $from->toDateString(),
            $to->toDateString(),
            $siteId ?? 'all',
        );

        return Cache::remember($key, self::TTL_SECONDS, fn () => $this->compute($from->utc(), $to->utc(), $siteId, $from, $to));
    }

    /**
     * @return array<string, mixed>
     */
    private function compute(CarbonImmutable $fromUtc, CarbonImmutable $toUtc, ?int $siteId, CarbonImmutable $fromLocal, CarbonImmutable $toLocal): array
    {
        $base = fn (): Builder => WorkOrder::query()->when($siteId, fn (Builder $q) => $q->where('site_id', $siteId));
        $finished = WorkOrderStatus::finishedValues();

        $byStatus = $base()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($n) => (int) $n);

        $resolved = $base()->whereBetween('resolved_at', [$fromUtc, $toUtc]);
        $resolvedCount = (clone $resolved)->count();
        $resolvedOnTime = (clone $resolved)->where('is_resolution_breached', false)->count();

        $responded = $base()->whereBetween('responded_at', [$fromUtc, $toUtc]);
        $respondedCount = (clone $responded)->count();
        $respondedOnTime = (clone $responded)->where('is_response_breached', false)->count();

        return [
            'now' => [
                'open' => $byStatus->except($finished)->sum(),
                'breached' => $base()->whereNotIn('status', $finished)->breached()->count(),
                'by_status' => $byStatus->all(),
            ],
            'period' => [
                'from' => $fromLocal->toDateString(),
                'to' => $toLocal->toDateString(),
                'created' => $base()->whereBetween('created_at', [$fromUtc, $toUtc])->count(),
                'resolved' => $resolvedCount,
                'closed' => $base()->whereBetween('closed_at', [$fromUtc, $toUtc])->count(),
                'resolution_compliance' => self::percent($resolvedOnTime, $resolvedCount),
                'response_compliance' => self::percent($respondedOnTime, $respondedCount),
            ],
            'technicians' => $this->technicians($base, $finished, $fromUtc, $toUtc),
            'top_categories' => $base()
                ->whereBetween('work_orders.created_at', [$fromUtc, $toUtc])
                ->join('categories', 'categories.id', '=', 'work_orders.category_id')
                ->selectRaw('categories.name as name, count(*) as total')
                ->groupBy('categories.name')
                ->orderByDesc('total')
                ->limit(self::TOP_CATEGORIES)
                ->get()
                ->map(fn ($row) => ['name' => (string) $row->getAttribute('name'), 'total' => (int) $row->getAttribute('total')])
                ->all(),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Two grouped, index-friendly queries instead of one conditional-sum scan
     * over every work order ever assigned: open jobs now uses
     * (tenant_id, assigned_to, status); the period uses (tenant_id, resolved_at).
     *
     * @param  \Closure(): Builder<WorkOrder>  $base
     * @param  list<string>  $finished
     * @return list<array{id: int, name: string, open: int, resolved: int, breached: int}>
     */
    private function technicians(\Closure $base, array $finished, CarbonImmutable $fromUtc, CarbonImmutable $toUtc): array
    {
        $open = $base()
            ->whereNotNull('assigned_to')
            ->whereNotIn('status', $finished)
            ->selectRaw('assigned_to, count(*) as total')
            ->groupBy('assigned_to')
            ->pluck('total', 'assigned_to');

        $resolved = $base()
            ->whereNotNull('assigned_to')
            ->whereBetween('resolved_at', [$fromUtc, $toUtc])
            ->selectRaw('assigned_to, count(*) as total, sum(case when is_resolution_breached = ? then 1 else 0 end) as late', [true])
            ->groupBy('assigned_to')
            ->get()
            ->keyBy('assigned_to');

        $ids = $open->keys()->merge($resolved->keys())->unique();
        $names = User::query()->withTrashed()->whereIn('id', $ids)->pluck('name', 'id');

        return $ids
            ->map(fn ($id) => [
                'id' => (int) $id,
                'name' => (string) ($names[$id] ?? 'Unknown'),
                'open' => (int) $open->get($id, 0),
                'resolved' => (int) ($resolved->get($id)?->getAttribute('total') ?? 0),
                'breached' => (int) ($resolved->get($id)?->getAttribute('late') ?? 0),
            ])
            ->sortBy([['open', 'desc'], ['name', 'asc']])
            ->values()
            ->all();
    }

    private static function percent(int $part, int $whole): ?float
    {
        return $whole === 0 ? null : round($part / $whole * 100, 1);
    }

    private static function versionKey(int $tenantId): string
    {
        return "dashboard-version:{$tenantId}";
    }
}
