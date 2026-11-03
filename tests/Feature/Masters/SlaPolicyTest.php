<?php

use App\Enums\UserRole;
use App\Models\SlaPolicy;

beforeEach(function () {
    $this->tenant = tenantWithRoles();
    $this->policy = SlaPolicy::factory()->create(['tenant_id' => $this->tenant->id]);
});

it('updates SLA targets', function () {
    actingAsRole(UserRole::TenantAdmin, $this->tenant);

    $this->put($this->tenant->url("sla-policies/{$this->policy->id}"), ['response_minutes' => 30, 'resolution_minutes' => 600])
        ->assertRedirect($this->tenant->url('sla-policies'));

    expect($this->policy->fresh())->response_minutes->toBe(30)->resolution_minutes->toBe(600);
});

it('requires resolution to be longer than response', function () {
    actingAsRole(UserRole::TenantAdmin, $this->tenant);

    $this->put($this->tenant->url("sla-policies/{$this->policy->id}"), ['response_minutes' => 60, 'resolution_minutes' => 60])
        ->assertSessionHasErrors('resolution_minutes');
});

it('shows supervisors the targets read-only', function () {
    actingAsRole(UserRole::Supervisor, $this->tenant);

    $this->get($this->tenant->url('sla-policies'))->assertOk()->assertSee('240 min');
    $this->put($this->tenant->url("sla-policies/{$this->policy->id}"), ['response_minutes' => 1, 'resolution_minutes' => 2])
        ->assertForbidden();
});

it('returns 404 for another tenant\'s policy', function () {
    actingAsRole(UserRole::TenantAdmin, $this->tenant);
    $foreign = SlaPolicy::factory()->create();

    $this->put($this->tenant->url("sla-policies/{$foreign->id}"), ['response_minutes' => 1, 'resolution_minutes' => 2])
        ->assertNotFound();
});
