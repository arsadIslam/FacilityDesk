<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderComment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkOrderComment>
 */
class WorkOrderCommentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'work_order_id' => WorkOrder::factory(),
            'tenant_id' => fn (array $a) => WorkOrder::query()->withoutGlobalScopes()->findOrFail($a['work_order_id'])->tenant_id,
            'user_id' => fn (array $a) => User::factory()->create(['tenant_id' => $a['tenant_id']])->id,
            'body' => fake()->sentence(),
            'is_internal' => false,
        ];
    }

    public function internal(): static
    {
        return $this->state(fn () => ['is_internal' => true]);
    }
}
