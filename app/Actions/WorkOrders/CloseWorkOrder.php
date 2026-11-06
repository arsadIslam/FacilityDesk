<?php

declare(strict_types=1);

namespace App\Actions\WorkOrders;

use App\Enums\WorkOrderStatus;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * The requester (or a supervisor) confirms a resolved work order is done.
 * Client ratings attach here in a later release.
 */
final class CloseWorkOrder
{
    public function __construct(private readonly ChangeStatus $changeStatus) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(WorkOrder $workOrder, User $actor, ?string $note = null): WorkOrder
    {
        return $this->changeStatus->handle($workOrder, WorkOrderStatus::Closed, $actor, $note);
    }
}
