<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

final class WorkOrderRaised extends WorkOrderNotification
{
    protected function message(): string
    {
        return "New {$this->workOrder->priority->label()} work order {$this->workOrder->number}";
    }

    public function toMail(object $notifiable): MailMessage
    {
        $workOrder = $this->workOrder->loadMissing(['site', 'location', 'raisedBy']);

        return (new MailMessage)
            ->subject($this->subject("New {$workOrder->priority->label()}: {$workOrder->title}"))
            ->line("{$workOrder->raisedBy->name} raised a work order at {$workOrder->site->name}.")
            ->line("**{$workOrder->title}**")
            ->line($workOrder->location->path)
            ->line('Respond by '.$this->siteTime($workOrder->response_due_at).'.')
            ->action('Assign a technician', $workOrder->url());
    }
}
