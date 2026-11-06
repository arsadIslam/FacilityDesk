<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

final class WorkOrderResolved extends WorkOrderNotification
{
    protected function message(): string
    {
        return "{$this->workOrder->number} was resolved: please confirm";
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->subject('Resolved: '.$this->workOrder->title))
            ->line('Your work order has been marked as resolved.')
            ->line("**{$this->workOrder->title}**")
            ->line($this->workOrder->resolution_note ?? '')
            ->line('Please confirm it is fixed, or reopen it if the problem persists.')
            ->action('Confirm or reopen', $this->workOrder->url());
    }
}
