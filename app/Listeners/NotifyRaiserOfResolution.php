<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\WorkOrderStatus;
use App\Events\WorkOrderStatusChanged;
use App\Jobs\Middleware\SetTenantContext;
use App\Notifications\WorkOrderResolved;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

final class NotifyRaiserOfResolution implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'default';

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    /**
     * @return list<object>
     */
    public function middleware(WorkOrderStatusChanged $event): array
    {
        return [new SetTenantContext($event->workOrder->tenant_id)];
    }

    /**
     * Only resolutions matter here; skipping in shouldQueue() keeps every
     * other status change off the queue entirely.
     */
    public function shouldQueue(WorkOrderStatusChanged $event): bool
    {
        return $event->to === WorkOrderStatus::Resolved;
    }

    public function handle(WorkOrderStatusChanged $event): void
    {
        $raiser = $event->workOrder->loadMissing('raisedBy')->raisedBy;

        // Nobody needs to be told about a resolution they made themselves.
        if (! $raiser->is_active || $raiser->is($event->changedBy)) {
            return;
        }

        $raiser->notify(new WorkOrderResolved($event->workOrder));
    }

    public function failed(WorkOrderStatusChanged $event, Throwable $exception): void
    {
        Log::error('Resolution notification failed', ['work_order_id' => $event->workOrder->id, 'error' => $exception->getMessage()]);
    }
}
