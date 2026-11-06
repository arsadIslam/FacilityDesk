<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\UserRole;
use App\Events\SlaBreached;
use App\Jobs\Middleware\SetTenantContext;
use App\Models\User;
use App\Notifications\SlaBreachAlert;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Alerts the site's supervisor and the tenant admins.
 */
final class NotifySupervisorOfBreach implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'notifications';

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    /**
     * @return list<object>
     */
    public function middleware(SlaBreached $event): array
    {
        return [new SetTenantContext($event->workOrder->tenant_id)];
    }

    public function handle(SlaBreached $event): void
    {
        $supervisorId = $event->workOrder->loadMissing('site')->site->supervisor_id;

        $recipients = User::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q
                ->whereKey($supervisorId ?? 0)
                ->orWhereIn('id', User::query()->role(UserRole::TenantAdmin->value)->select('users.id')))
            ->get();

        Notification::send($recipients, new SlaBreachAlert($event->workOrder, $event->breaches));
    }

    public function failed(SlaBreached $event, Throwable $exception): void
    {
        Log::error('SLA breach alert failed', ['work_order_id' => $event->workOrder->id, 'error' => $exception->getMessage()]);
    }
}
