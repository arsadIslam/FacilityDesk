<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Models\User;
use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Notifications\Notification;

/**
 * Stamps database notifications with the recipient's tenant, so they can be
 * scoped, reported on and purged per tenant like every other tenant row.
 */
final class TenantDatabaseChannel extends DatabaseChannel
{
    /**
     * @return array<string, mixed>
     */
    protected function buildPayload($notifiable, Notification $notification): array
    {
        return [
            ...parent::buildPayload($notifiable, $notification),
            'tenant_id' => $notifiable instanceof User ? $notifiable->tenant_id : null,
        ];
    }
}
