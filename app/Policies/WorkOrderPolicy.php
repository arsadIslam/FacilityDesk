<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Enums\WorkOrderStatus;
use App\Models\User;
use App\Models\WorkOrder;

/**
 * Cross-tenant records never reach this policy: the tenant scope turns them
 * into 404s during route-model binding.
 */
final class WorkOrderPolicy
{
    /**
     * Every tenant user has a work-order list; WorkOrder::visibleTo() narrows it.
     */
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function view(User $user, WorkOrder $workOrder): bool
    {
        if ($user->hasPermission(Permission::ViewAllWorkOrders)
            || $workOrder->raised_by === $user->id
            || $workOrder->assigned_to === $user->id) {
            return true;
        }

        return $user->hasUserRole(UserRole::ClientUser)
            && $user->client_id !== null
            && $workOrder->loadMissing('site')->site->client_id === $user->client_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::CreateWorkOrders);
    }

    public function assign(User $user, WorkOrder $workOrder): bool
    {
        return $this->view($user, $workOrder)
            && $user->hasPermission(Permission::AssignWorkOrders)
            && in_array($workOrder->status, [WorkOrderStatus::Open, WorkOrderStatus::Assigned], true);
    }

    /**
     * Who may move $workOrder to $to. Whether the move itself is legal is
     * WorkOrderStatus::canTransitionTo()'s job, checked by ChangeStatus.
     */
    public function transition(User $user, WorkOrder $workOrder, WorkOrderStatus $to): bool
    {
        if (! $this->view($user, $workOrder)) {
            return false;
        }

        $isReopen = $workOrder->status === WorkOrderStatus::Resolved && $to === WorkOrderStatus::Assigned;

        return match (true) {
            $to === WorkOrderStatus::Cancelled => $user->hasPermission(Permission::CancelWorkOrders),
            $to === WorkOrderStatus::Closed, $isReopen => $user->hasPermission(Permission::CloseWorkOrders),
            $to === WorkOrderStatus::Assigned => $user->hasPermission(Permission::AssignWorkOrders),
            default => $user->hasPermission(Permission::WorkOnWorkOrders)
                && ($user->hasPermission(Permission::ViewAllWorkOrders) || $workOrder->assigned_to === $user->id),
        };
    }

    /**
     * Before/after photos come from the people doing the work. Requesters
     * attach photos when they raise the work order (see create()).
     */
    public function upload(User $user, WorkOrder $workOrder): bool
    {
        return $this->view($user, $workOrder)
            && ! $workOrder->status->isFinished()
            && $user->hasPermission(Permission::WorkOnWorkOrders)
            && ($user->hasPermission(Permission::ViewAllWorkOrders) || $workOrder->assigned_to === $user->id);
    }

    public function comment(User $user, WorkOrder $workOrder): bool
    {
        return $this->view($user, $workOrder);
    }

    public function viewInternalComments(User $user): bool
    {
        return $user->hasPermission(Permission::InternalComments);
    }
}
