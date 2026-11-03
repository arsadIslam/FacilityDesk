<?php

namespace Database\Factories;

use App\Enums\LocationType;
use App\Models\Location;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Use the SaveLocation action for nested trees; this factory makes root nodes.
 *
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'Block '.fake()->unique()->bothify('?#');

        return [
            'site_id' => Site::factory(),
            'tenant_id' => fn (array $attributes) => Site::query()->withoutGlobalScopes()->findOrFail($attributes['site_id'])->tenant_id,
            'parent_id' => null,
            'type' => LocationType::Building,
            'name' => $name,
            'path' => $name,
        ];
    }

    public function forSite(Site $site): static
    {
        return $this->state(fn () => ['site_id' => $site->id, 'tenant_id' => $site->tenant_id]);
    }
}
