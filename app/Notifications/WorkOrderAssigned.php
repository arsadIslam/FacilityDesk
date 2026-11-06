<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

final class WorkOrderAssigned extends WorkOrderNotification
{
    protected function message(): string
    {
        return "{$this->workOrder->number} was assigned to you";
    }

    public function toMail(object $notifiable): MailMessage
    {
        $workOrder = $this->workOrder->loadMissing(['site', 'location']);

        return (new MailMessage)
            ->subject($this->subject('Assigned to you: '.$workOrder->title))
            ->line("A {$workOrder->priority->label()} priority work order has been assigned to you.")
            ->line("**{$workOrder->title}**")
            ->line("{$workOrder->site->name} · {$workOrder->location->path}")
            ->line('Resolve by '.$this->siteTime($workOrder->resolution_due_at).'.')
            ->action('Open work order', $workOrder->url());
    }
}
