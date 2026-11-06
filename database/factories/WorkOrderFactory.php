<?php

namespace Database\Factories;

use App\Enums\Priority;
use App\Enums\WorkOrderSource;
use App\Enums\WorkOrderStatus;
use App\Models\Category;
use App\Models\Location;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Every related row is created inside the work order's tenant.
 *
 * @extends Factory<WorkOrder>
 */
class WorkOrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'site_id' => fn (array $a) => Site::factory()->create(['tenant_id' => $a['tenant_id']])->id,
            'location_id' => fn (array $a) => Location::factory()->create(['tenant_id' => $a['tenant_id'], 'site_id' => $a['site_id']])->id,
            'category_id' => fn (array $a) => Category::factory()->create(['tenant_id' => $a['tenant_id']])->id,
            'raised_by' => fn (array $a) => User::factory()->create(['tenant_id' => $a['tenant_id']])->id,
            'number' => fake()->unique()->numerify('WO-2026-#####'),
            'title' => fake()->sentence(5),
            'description' => fake()->paragraph(),
            'priority' => Priority::Medium,
            'status' => WorkOrderStatus::Open,
            'source' => WorkOrderSource::Web,
            'response_due_at' => now()->addHours(4),
            'resolution_due_at' => now()->addDay(),
        ];
    }

    public function forSite(Site $site): static
    {
        return $this->state(fn () => ['tenant_id' => $site->tenant_id, 'site_id' => $site->id]);
    }

    public function raisedBy(User $user): static
    {
        return $this->state(fn () => ['tenant_id' => $user->tenant_id, 'raised_by' => $user->id]);
    }

    public function assignedTo(User $technician, WorkOrderStatus $status = WorkOrderStatus::Assigned): static
    {
        return $this->state(fn () => [
            'tenant_id' => $technician->tenant_id,
            'assigned_to' => $technician->id,
            'status' => $status,
        ]);
    }

    public function status(WorkOrderStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
