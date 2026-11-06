<?php

declare(strict_types=1);

namespace App\Actions\WorkOrders;

use App\Enums\Priority;
use App\Enums\WorkOrderSource;
use App\Enums\WorkOrderStatus;
use App\Events\WorkOrderCreated;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Location;
use App\Models\Site;
use App\Models\SlaPolicy;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\SlaCalculator;
use App\Services\WorkOrderNumberGenerator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use LogicException;

final class CreateWorkOrder
{
    public function __construct(
        private readonly WorkOrderNumberGenerator $numbers,
        private readonly SlaCalculator $sla,
    ) {}

    /**
     * $priority null means "use the category's default".
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(
        User $raisedBy,
        Site $site,
        Location $location,
        Category $category,
        string $title,
        ?string $description = null,
        ?Priority $priority = null,
        WorkOrderSource $source = WorkOrderSource::Web,
        ?Asset $asset = null,
    ): WorkOrder {
        Gate::forUser($raisedBy)->authorize('create', WorkOrder::class);

        // Defence in depth: the request rules already enforce this.
        if ($location->site_id !== $site->id) {
            throw ValidationException::withMessages(['location_id' => 'The location must belong to the selected site.']);
        }

        if ($asset !== null && $asset->site_id !== $site->id) {
            throw ValidationException::withMessages(['asset_id' => 'The asset must belong to the selected site.']);
        }

        $workOrder = DB::transaction(function () use ($raisedBy, $site, $location, $category, $title, $description, $priority, $source, $asset): WorkOrder {
            $now = now();
            $priority ??= $category->default_priority;
            $targets = SlaPolicy::targetsFor($priority);
            $due = $this->sla->dueDates($now, $targets['response'], $targets['resolution']);

            $workOrder = new WorkOrder(['title' => $title, 'description' => $description]);
            $workOrder->forceFill([
                'tenant_id' => $site->tenant_id,
                'number' => $this->numbers->next($this->tenantOf($site), $now),
                'site_id' => $site->id,
                'location_id' => $location->id,
                'category_id' => $category->id,
                'asset_id' => $asset?->id,
                'raised_by' => $raisedBy->id,
                'priority' => $priority,
                'status' => WorkOrderStatus::Open,
                'source' => $source,
                ...$due,
            ])->save();

            $workOrder->recordStatus(null, WorkOrderStatus::Open, $raisedBy);

            return $workOrder;
        });

        WorkOrderCreated::dispatch($workOrder);

        return $workOrder;
    }

    private function tenantOf(Site $site): Tenant
    {
        $tenant = $site->loadMissing('tenant')->getRelation('tenant');

        return $tenant instanceof Tenant ? $tenant : throw new LogicException('Site has no tenant.');
    }
}
