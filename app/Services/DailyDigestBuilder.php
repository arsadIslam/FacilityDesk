<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\WorkOrderStatus;
use App\Models\Tenant;
use App\Models\WorkOrder;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * The numbers in a supervisor's morning email, for the tenant in context.
 * "Today" and "yesterday" are the tenant's local days, converted to UTC.
 */
final class DailyDigestBuilder
{
    public const OVERDUE_LIST_LIMIT = 10;

    /**
     * @return array{date: string, open: int, unassigned: int, breached: int, due_today: int, resolved_yesterday: int, overdue: list<array{number: string, title: string, site: string, path: string}>}
     */
    public function build(Tenant $tenant, CarbonImmutable $now): array
    {
        $local = $now->setTimezone($tenant->timezone);
        $todayStart = $local->startOfDay()->utc();
        $todayEnd = $local->endOfDay()->utc();
        $yesterdayStart = $local->subDay()->startOfDay()->utc();

        $open = fn (): Builder => WorkOrder::query()->whereNotIn('status', WorkOrderStatus::finishedValues());

        return [
            'date' => $local->toDateString(),
            'open' => $open()->count(),
            'unassigned' => WorkOrder::query()->where('status', WorkOrderStatus::Open->value)->count(),
            'breached' => $open()->breached()->count(),
            'due_today' => $open()->whereBetween('resolution_due_at', [$todayStart, $todayEnd])->count(),
            'resolved_yesterday' => WorkOrder::query()->whereBetween('resolved_at', [$yesterdayStart, $todayStart])->count(),
            'overdue' => $open()
                ->breached()
                ->with('site')
                ->orderBy('resolution_due_at')
                ->limit(self::OVERDUE_LIST_LIMIT)
                ->get()
                ->map(fn (WorkOrder $workOrder) => [
                    'number' => $workOrder->number,
                    'title' => $workOrder->title,
                    'site' => $workOrder->site->name,
                    'path' => $workOrder->path(),
                ])
                ->values()
                ->all(),
        ];
    }
}
