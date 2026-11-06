<?php

namespace Database\Factories;

use App\Enums\WorkOrderStatus;
use App\Models\WorkOrder;
use App\Models\WorkOrderStatusHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Mainly for docs and fixtures: real history rows come from WorkOrder::recordStatus().
 *
 * @extends Factory<WorkOrderStatusHistory>
 */
class WorkOrderStatusHistoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'work_order_id' => WorkOrder::factory(),
            'tenant_id' => fn (array $a) => WorkOrder::query()->withoutGlobalScopes()->findOrFail($a['work_order_id'])->tenant_id,
            'from_status' => WorkOrderStatus::Open,
            'to_status' => WorkOrderStatus::Assigned,
            'changed_by' => fn (array $a) => WorkOrder::query()->withoutGlobalScopes()->findOrFail($a['work_order_id'])->raised_by,
            'note' => null,
        ];
    }
}
