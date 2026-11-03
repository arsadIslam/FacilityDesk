<?php

use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;

beforeEach(function () {
    $this->tenantA = Tenant::factory()->create();
    $this->tenantB = Tenant::factory()->create();

    $this->userA = User::factory()->forTenant($this->tenantA)->create();
    $this->userB = User::factory()->forTenant($this->tenantB)->create();
});

it('only returns rows of the tenant in context', function () {
    app(TenantContext::class)->set($this->tenantA);

    expect(User::pluck('id')->all())->toBe([$this->userA->id])
        ->and(User::count())->toBe(1);
});

it('applies the scope to relationship and aggregate queries', function () {
    app(TenantContext::class)->set($this->tenantA);

    expect($this->tenantB->users()->count())->toBe(0)
        ->and(User::where('email', $this->userB->email)->exists())->toBeFalse();
});

it('treats another tenant\'s record as not found', function () {
    app(TenantContext::class)->set($this->tenantA);

    User::findOrFail($this->userB->id);
})->throws(ModelNotFoundException::class);

it('does not scope queries when no tenant is in context', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    expect(User::count())->toBe(3)
        ->and(User::find($superAdmin->id))->not->toBeNull();
});

it('can bypass the scope explicitly for cross-tenant work', function () {
    app(TenantContext::class)->set($this->tenantA);

    expect(User::withoutGlobalScope('tenant')->count())->toBe(2);
});

it('stamps the tenant in context on create', function () {
    app(TenantContext::class)->set($this->tenantA);

    $user = User::create([
        'name' => 'New User',
        'email' => 'new@example.com',
        'password' => 'secret-password',
    ]);

    expect($user->tenant_id)->toBe($this->tenantA->id);
});

it('never lets tenant_id be mass assigned', function () {
    app(TenantContext::class)->set($this->tenantA);

    $user = User::create([
        'tenant_id' => $this->tenantB->id,
        'name' => 'Sneaky',
        'email' => 'sneaky@example.com',
        'password' => 'secret-password',
    ]);

    expect($user->tenant_id)->toBe($this->tenantA->id);
});

it('allows the same email in different tenants', function () {
    $user = User::factory()->forTenant($this->tenantB)->create(['email' => $this->userA->email]);

    expect($user->exists)->toBeTrue();
});

it('rejects a duplicate email inside the same tenant', function () {
    User::factory()->forTenant($this->tenantA)->create(['email' => $this->userA->email]);
})->throws(QueryException::class);
