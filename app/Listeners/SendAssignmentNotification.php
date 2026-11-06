<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\WorkOrderAssigned;
use App\Jobs\Middleware\SetTenantContext;
use App\Notifications\WorkOrderAssigned as WorkOrderAssignedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SendAssignmentNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'notifications';

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    /**
     * @return list<object>
     */
    public function middleware(WorkOrderAssigned $event): array
    {
        return [new SetTenantContext($event->workOrder->tenant_id)];
    }

    public function handle(WorkOrderAssigned $event): void
    {
        if (! $event->assignee->is_active) {
            return;
        }

        $event->assignee->notify(new WorkOrderAssignedNotification($event->workOrder));
    }

    public function failed(WorkOrderAssigned $event, Throwable $exception): void
    {
        Log::error('Assignment notification failed', ['work_order_id' => $event->workOrder->id, 'error' => $exception->getMessage()]);
    }
}
