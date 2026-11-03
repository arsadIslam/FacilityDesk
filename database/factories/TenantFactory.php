<?php

namespace Database\Factories;

use App\Enums\TenantPlan;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => fake()->unique()->regexify('[a-z]{6}[0-9]{4}'),
            'email' => fake()->companyEmail(),
            'phone' => fake()->numerify('9#########'),
            'plan' => TenantPlan::Trial,
            'status' => TenantStatus::Active,
            'timezone' => 'Asia/Kolkata',
            'trial_ends_at' => now()->addDays(14),
        ];
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => TenantStatus::Suspended]);
    }
}
