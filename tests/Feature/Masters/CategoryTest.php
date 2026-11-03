<?php

use App\Enums\Priority;
use App\Enums\UserRole;
use App\Models\Category;

beforeEach(function () {
    $this->tenant = tenantWithRoles();
    actingAsRole(UserRole::TenantAdmin, $this->tenant);
});

it('creates and updates a category', function () {
    $this->post($this->tenant->url('categories'), ['name' => 'Lifts', 'default_priority' => 'high', 'is_active' => '1'])
        ->assertRedirect($this->tenant->url('categories'));

    $category = Category::sole();
    expect($category->default_priority)->toBe(Priority::High)->and($category->tenant_id)->toBe($this->tenant->id);

    $this->put($this->tenant->url("categories/{$category->id}"), ['name' => 'Elevators', 'default_priority' => 'critical'])
        ->assertSessionHasNoErrors();

    expect($category->fresh())
        ->name->toBe('Elevators')
        ->default_priority->toBe(Priority::Critical)
        ->is_active->toBeFalse();
});

it('keeps names unique per tenant only', function () {
    Category::factory()->forTenant($this->tenant)->create(['name' => 'Lifts']);
    Category::factory()->create(['name' => 'Pest control']);

    $this->post($this->tenant->url('categories'), ['name' => 'Lifts', 'default_priority' => 'low'])->assertSessionHasErrors('name');
    $this->post($this->tenant->url('categories'), ['name' => 'Pest control', 'default_priority' => 'low'])->assertSessionHasNoErrors();
});

it('rejects an unknown priority', function () {
    $this->post($this->tenant->url('categories'), ['name' => 'Lifts', 'default_priority' => 'urgent'])
        ->assertSessionHasErrors('default_priority');
});

it('returns 404 for another tenant\'s category', function () {
    $foreign = Category::factory()->create();

    $this->get($this->tenant->url("categories/{$foreign->id}/edit"))->assertNotFound();
});
