<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\SlaBreached;
use App\Events\WorkOrderAssigned;
use App\Events\WorkOrderCreated;
use App\Events\WorkOrderStatusChanged;
use App\Services\DashboardStats;

/**
 * Deliberately not queued: it is one cache write, and running it inline
 * means the dashboard reflects a change on the very next page load. The
 * events themselves dispatch after commit, so a rolled-back change never
 * busts anything.
 */
final class BustDashboardCache
{
    public function handle(WorkOrderCreated|WorkOrderAssigned|WorkOrderStatusChanged|SlaBreached $event): void
    {
        DashboardStats::bust($event->workOrder->tenant_id);
    }
}
