<?php

declare(strict_types=1);

namespace App\Actions\WorkOrders;

use App\Enums\UserRole;
use App\Enums\WorkOrderStatus;
use App\Events\WorkOrderAssigned;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Assigns (open → assigned) or reassigns (assigned → assigned) a technician.
 */
final class AssignWorkOrder
{
    public function __construct(private readonly ChangeStatus $changeStatus) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(WorkOrder $workOrder, User $assignee, User $actor, ?string $note = null): WorkOrder
    {
        if ($assignee->tenant_id !== $workOrder->tenant_id
            || ! $assignee->is_active
            || ! $assignee->hasUserRole(UserRole::Technician)) {
            throw ValidationException::withMessages(['assignee_id' => 'Choose an active technician.']);
        }

        if ($workOrder->status === WorkOrderStatus::Assigned && $workOrder->assigned_to === $assignee->id) {
            throw ValidationException::withMessages(['assignee_id' => "{$assignee->name} is already assigned."]);
        }

        $previousAssigneeId = $workOrder->assigned_to;

        $workOrder = $this->changeStatus->handle($workOrder, WorkOrderStatus::Assigned, $actor, $note, [
            'assigned_to' => $assignee->id,
            'assigned_by' => $actor->id,
        ]);

        $previousAssignee = $previousAssigneeId === null ? null : User::query()->find($previousAssigneeId);

        WorkOrderAssigned::dispatch($workOrder, $assignee, $previousAssignee, $actor);

        return $workOrder;
    }
}
