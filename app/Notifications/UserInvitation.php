<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class UserInvitation extends Notification
{
    public function __construct(
        public readonly string $tenantName,
        public readonly string $url,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("You're invited to {$this->tenantName} on FacilityDesk")
            ->line("You've been invited to join {$this->tenantName} on FacilityDesk.")
            ->action('Set your password', $this->url)
            ->line('This link expires in '.config('tenancy.invitation_expiry_hours').' hours.');
    }
}
