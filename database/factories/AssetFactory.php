<?php

namespace Database\Factories;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Location;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Site, location and category all come from the asset's tenant.
 *
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'tenant_id' => fn (array $a) => Site::query()->withoutGlobalScopes()->findOrFail($a['site_id'])->tenant_id,
            'location_id' => fn (array $a) => Location::factory()->create(['tenant_id' => $a['tenant_id'], 'site_id' => $a['site_id']])->id,
            'category_id' => fn (array $a) => Category::factory()->create(['tenant_id' => $a['tenant_id']])->id,
            'name' => fake()->randomElement(['AC unit', 'Water pump', 'Lift', 'DG set', 'Fire panel']).' '.fake()->numberBetween(1, 20),
            'code' => fake()->unique()->bothify('AST-####'),
            'make' => fake()->company(),
            'model' => fake()->bothify('M-###'),
            'serial_no' => fake()->bothify('SN########'),
            'installed_at' => now()->subYears(2)->toDateString(),
            'warranty_until' => now()->addYear()->toDateString(),
            'status' => AssetStatus::Working,
        ];
    }

    public function at(Location $location): static
    {
        return $this->state(fn () => [
            'tenant_id' => $location->tenant_id,
            'site_id' => $location->site_id,
            'location_id' => $location->id,
        ]);
    }
}
