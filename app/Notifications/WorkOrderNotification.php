<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\WorkOrder;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Common shape for work order notifications: mail plus a database row the
 * notification bell renders as "message" linking to "path".
 *
 * Not queued itself: it is sent from a queued listener, so queueing it again
 * would only add a second hop.
 */
abstract class WorkOrderNotification extends Notification
{
    public function __construct(public readonly WorkOrder $workOrder) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    abstract protected function message(): string;

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'work_order_id' => $this->workOrder->id,
            'number' => $this->workOrder->number,
            'title' => $this->workOrder->title,
            'message' => $this->message(),
            // A path, not a URL: the bell redirects to it on the current subdomain.
            'path' => $this->workOrder->path(),
        ];
    }

    protected function subject(string $text): string
    {
        return "[{$this->workOrder->number}] {$text}";
    }

    protected function siteTime(\DateTimeInterface $time): string
    {
        $timezone = $this->workOrder->loadMissing('site')->site->timezone;

        return Carbon::instance($time)->timezone($timezone)->format('D d M, H:i T');
    }
}
