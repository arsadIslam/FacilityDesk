<?php

declare(strict_types=1);

namespace App\Actions\WorkOrders;

use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderComment;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

final class AddComment
{
    /**
     * @throws AuthorizationException
     */
    public function handle(WorkOrder $workOrder, User $author, string $body, bool $internal = false): WorkOrderComment
    {
        $gate = Gate::forUser($author);
        $gate->authorize('comment', $workOrder);

        if ($internal) {
            $gate->authorize('viewInternalComments', WorkOrder::class);
        }

        $comment = new WorkOrderComment(['body' => $body]);
        $comment->tenant_id = $workOrder->tenant_id;
        $comment->user_id = $author->id;
        $comment->is_internal = $internal;

        $workOrder->comments()->save($comment);

        return $comment;
    }
}
