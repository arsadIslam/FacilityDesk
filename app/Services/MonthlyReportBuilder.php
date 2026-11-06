<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\WorkOrderStatus;
use App\Models\Client;
use App\Models\Tenant;
use App\Models\WorkOrder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Everything one client's monthly report shows, for the tenant in context.
 * The month is the tenant's local calendar month.
 */
final class MonthlyReportBuilder
{
    /**
     * @return array{
     *     tenant: Tenant, client: Client, month: CarbonImmutable,
     *     totals: array{raised: int, resolved: int, closed: int, still_open: int, response_breached: int, resolution_breached: int, resolution_compliance: float|null, avg_resolution_hours: float|null},
     *     by_site: list<array{site: string, raised: int, resolved: int, breached: int}>,
     *     by_category: list<array{category: string, raised: int}>,
     *     rows: Collection<int, WorkOrder>
     * }
     */
    public function build(Tenant $tenant, Client $client, CarbonImmutable $month): array
    {
        $start = $month->setTimezone($tenant->timezone)->startOfMonth();
        $end = $start->endOfMonth();

        $rows = WorkOrder::query()
            ->whereIn('site_id', $client->sites()->withTrashed()->select('id'))
            ->whereBetween('created_at', [$start->utc(), $end->utc()])
            ->with(['site', 'location', 'category', 'assignee'])
            ->orderBy('created_at')
            ->get();

        $resolved = $rows->whereNotNull('resolved_at');
        $hours = $resolved
            ->map(fn (WorkOrder $wo) => $wo->created_at->diffInMinutes($wo->resolved_at) / 60 - $wo->hold_minutes / 60)
            ->filter(fn (float $h) => $h >= 0);

        return [
            'tenant' => $tenant,
            'client' => $client,
            'month' => $start,
            'totals' => [
                'raised' => $rows->count(),
                'resolved' => $resolved->count(),
                'closed' => $rows->where('status', WorkOrderStatus::Closed)->count(),
                'still_open' => $rows->reject(fn (WorkOrder $wo) => $wo->status->isFinished())->count(),
                'response_breached' => $rows->where('is_response_breached', true)->count(),
                'resolution_breached' => $rows->where('is_resolution_breached', true)->count(),
                'resolution_compliance' => $resolved->isEmpty() ? null : round($resolved->where('is_resolution_breached', false)->count() / $resolved->count() * 100, 1),
                'avg_resolution_hours' => $hours->isEmpty() ? null : round($hours->avg(), 1),
            ],
            'by_site' => $rows->groupBy(fn (WorkOrder $wo) => $wo->site->name)
                ->map(fn (Collection $group, string $site) => [
                    'site' => $site,
                    'raised' => $group->count(),
                    'resolved' => $group->whereNotNull('resolved_at')->count(),
                    'breached' => $group->filter(fn (WorkOrder $wo) => $wo->is_response_breached || $wo->is_resolution_breached)->count(),
                ])->sortKeys()->values()->all(),
            'by_category' => $rows->groupBy(fn (WorkOrder $wo) => $wo->category->name)
                ->map(fn (Collection $group, string $category) => ['category' => $category, 'raised' => $group->count()])
                ->sortByDesc('raised')->values()->all(),
            'rows' => $rows,
        ];
    }
}
