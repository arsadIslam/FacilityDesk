<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\UserRole;
use App\Events\WorkOrderCreated;
use App\Jobs\Middleware\SetTenantContext;
use App\Models\User;
use App\Notifications\WorkOrderRaised;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Tells the people who will dispatch the job: the site's supervisor and
 * supervisors covering the site, or every supervisor if the site has none.
 */
final class NotifySupervisorsOfNewWorkOrder implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'default';

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    /**
     * @return list<object>
     */
    public function middleware(WorkOrderCreated $event): array
    {
        return [new SetTenantContext($event->workOrder->tenant_id)];
    }

    public function handle(WorkOrderCreated $event): void
    {
        $workOrder = $event->workOrder;
        $recipients = $this->recipients($workOrder->site_id, $workOrder->raised_by);

        Notification::send($recipients, new WorkOrderRaised($workOrder));
    }

    public function failed(WorkOrderCreated $event, Throwable $exception): void
    {
        Log::error('New work order notification failed', ['work_order_id' => $event->workOrder->id, 'error' => $exception->getMessage()]);
    }

    /**
     * @return Collection<int, User>
     */
    private function recipients(int $siteId, int $raisedBy): Collection
    {
        $supervisors = fn () => User::query()
            ->where('is_active', true)
            ->whereKeyNot($raisedBy)
            ->role(UserRole::Supervisor->value);

        $forSite = $supervisors()
            ->where(fn ($q) => $q
                ->whereHas('sites', fn ($sites) => $sites->whereKey($siteId))
                ->orWhereIn('id', fn ($sub) => $sub->select('supervisor_id')->from('sites')->where('id', $siteId)))
            ->get();

        return $forSite->isNotEmpty() ? $forSite : $supervisors()->get();
    }
}
