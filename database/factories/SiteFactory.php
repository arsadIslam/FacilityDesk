<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Site;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Site>
 */
class SiteFactory extends Factory
{
    /**
     * The client always belongs to the same tenant as the site.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'client_id' => fn (array $attributes) => Client::factory()->create(['tenant_id' => $attributes['tenant_id']])->id,
            'name' => fake()->streetName().' Campus',
            'code' => fake()->unique()->bothify('???-##'),
            'address' => fake()->address(),
            'city' => fake()->city(),
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ];
    }

    public function forClient(Client $client): static
    {
        return $this->state(fn () => ['tenant_id' => $client->tenant_id, 'client_id' => $client->id]);
    }
}
