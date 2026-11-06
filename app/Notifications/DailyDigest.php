<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Tenant;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Mail only: a summary, not something to act on from the bell.
 */
final class DailyDigest extends Notification
{
    /**
     * @param  array{date: string, open: int, unassigned: int, breached: int, due_today: int, resolved_yesterday: int, overdue: list<array{number: string, title: string, site: string, path: string}>}  $stats
     */
    public function __construct(
        public readonly Tenant $tenant,
        public readonly array $stats,
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
        $s = $this->stats;

        $mail = (new MailMessage)
            ->subject("[{$this->tenant->name}] Daily summary for {$s['date']}: {$s['open']} open, {$s['breached']} breached")
            ->greeting('Good morning')
            ->line("**{$s['open']}** open work orders, **{$s['unassigned']}** waiting for a technician.")
            ->line("**{$s['due_today']}** due today · **{$s['breached']}** past their SLA · **{$s['resolved_yesterday']}** resolved yesterday.");

        if ($s['overdue'] !== []) {
            $mail->line('Most overdue:');

            foreach ($s['overdue'] as $workOrder) {
                $mail->line("- [{$workOrder['number']}]({$this->tenant->url($workOrder['path'])}) {$workOrder['title']} ({$workOrder['site']})");
            }
        }

        return $mail->action('Open breached work orders', $this->tenant->url('work-orders?breached=1'));
    }
}
