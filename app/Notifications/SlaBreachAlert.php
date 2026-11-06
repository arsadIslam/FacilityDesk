<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\WorkOrder;
use Illuminate\Notifications\Messages\MailMessage;

final class SlaBreachAlert extends WorkOrderNotification
{
    /**
     * @param  list<'response'|'resolution'>  $breaches
     */
    public function __construct(WorkOrder $workOrder, public readonly array $breaches)
    {
        parent::__construct($workOrder);
    }

    protected function message(): string
    {
        return "{$this->workOrder->number} breached its {$this->targets()} SLA";
    }

    public function toMail(object $notifiable): MailMessage
    {
        $workOrder = $this->workOrder->loadMissing(['site', 'assignee']);

        return (new MailMessage)
            ->error()
            ->subject($this->subject("SLA breached ({$this->targets()}): {$workOrder->title}"))
            ->line("This {$workOrder->priority->label()} priority work order at {$workOrder->site->name} has missed its {$this->targets()} target.")
            ->line("**{$workOrder->title}**")
            ->line('Status: '.$workOrder->status->label().' · Assignee: '.($workOrder->assignee->name ?? 'unassigned'))
            ->line(in_array('resolution', $this->breaches, true)
                ? 'Resolution was due '.$this->siteTime($workOrder->resolution_due_at).'.'
                : 'A response was due '.$this->siteTime($workOrder->response_due_at).'.')
            ->action('Open work order', $workOrder->url());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [...parent::toArray($notifiable), 'breaches' => $this->breaches];
    }

    private function targets(): string
    {
        return implode(' and ', $this->breaches);
    }
}
