<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class WorkOrderAssigned implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly WorkOrder $workOrder,
        public readonly User $assignee,
        public readonly ?User $previousAssignee,
        public readonly User $assignedBy,
    ) {}
}
