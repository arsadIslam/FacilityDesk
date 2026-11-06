<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Attachment;
use App\Models\User;
use App\Models\WorkOrder;

final class AttachmentPolicy
{
    /**
     * Anyone who can see the work order can see its photos.
     */
    public function view(User $user, Attachment $attachment): bool
    {
        $attachable = $attachment->loadMissing('attachable')->attachable;

        return $attachable instanceof WorkOrder && $user->can('view', $attachable);
    }
}
