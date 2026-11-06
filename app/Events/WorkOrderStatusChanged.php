<?php

declare(strict_types=1);

namespace App\Events;

use App\Enums\WorkOrderStatus;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched after commit, so listeners never act on a rolled-back change.
 */
final class WorkOrderStatusChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly WorkOrder $workOrder,
        public readonly WorkOrderStatus $from,
        public readonly WorkOrderStatus $to,
        public readonly ?User $changedBy,
    ) {}
}
