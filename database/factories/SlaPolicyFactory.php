<?php

namespace Database\Factories;

use App\Enums\Priority;
use App\Models\SlaPolicy;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SlaPolicy>
 */
class SlaPolicyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'priority' => Priority::Medium,
            'response_minutes' => Priority::Medium->defaultSla()['response'],
            'resolution_minutes' => Priority::Medium->defaultSla()['resolution'],
        ];
    }
}
