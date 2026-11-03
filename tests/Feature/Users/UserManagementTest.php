<?php

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Client;
use App\Models\User;
use App\Services\TenantContext;

beforeEach(function () {
    $this->tenant = tenantWithRoles();
    $this->admin = actingAsRole(UserRole::TenantAdmin, $this->tenant);
});

function roleOf(User $user): ?UserRole
{
    return app(TenantContext::class)->run($user->tenant()->sole(), fn () => $user->fresh()->role());
}

it('lists only this tenant\'s users', function () {
    $colleague = userWithRole(UserRole::Technician, $this->tenant, ['name' => 'Colleague Carl']);
    $outsider = userWithRole(UserRole::Technician, null, ['name' => 'Outsider Olga']);

    $this->get($this->tenant->url('users'))
        ->assertOk()
        ->assertSee('Colleague Carl')
        ->assertDontSee('Outsider Olga');
});

it('changes a user\'s role and client', function () {
    $user = userWithRole(UserRole::Technician, $this->tenant);
    $client = Client::factory()->forTenant($this->tenant)->create();

    $this->put($this->tenant->url("users/{$user->id}"), [
        'name' => 'Renamed',
        'role' => UserRole::ClientUser->value,
        'client_id' => $client->id,
    ])->assertRedirect($this->tenant->url('users'));

    expect(roleOf($user))->toBe(UserRole::ClientUser)
        ->and($user->fresh()->client_id)->toBe($client->id)
        ->and($user->fresh()->name)->toBe('Renamed');
});

it('syncs technician skills and drops them on a role change', function () {
    $technician = userWithRole(UserRole::Technician, $this->tenant);
    $skills = Category::factory()->forTenant($this->tenant)->count(2)->create();

    $this->put($this->tenant->url("users/{$technician->id}"), [
        'name' => $technician->name,
        'role' => 'technician',
        'skill_ids' => $skills->modelKeys(),
    ])->assertSessionHasNoErrors();

    expect($technician->skills()->pluck('categories.id')->all())->toEqualCanonicalizing($skills->modelKeys());
    $this->assertDatabaseHas('technician_skills', ['user_id' => $technician->id, 'tenant_id' => $this->tenant->id]);

    $this->put($this->tenant->url("users/{$technician->id}"), ['name' => $technician->name, 'role' => 'supervisor', 'skill_ids' => $skills->modelKeys()]);

    expect($technician->skills()->count())->toBe(0);
});

it('rejects skills from another tenant', function () {
    $technician = userWithRole(UserRole::Technician, $this->tenant);
    $foreign = Category::factory()->create();

    $this->put($this->tenant->url("users/{$technician->id}"), [
        'name' => $technician->name,
        'role' => 'technician',
        'skill_ids' => [$foreign->id],
    ])->assertSessionHasErrors('skill_ids.0');
});

it('does not let admins change their own role', function () {
    $this->put($this->tenant->url("users/{$this->admin->id}"), ['name' => $this->admin->name, 'role' => 'technician'])
        ->assertSessionHasErrors('role');

    expect(roleOf($this->admin))->toBe(UserRole::TenantAdmin);
});

it('deactivates and reactivates a user', function () {
    $user = userWithRole(UserRole::Technician, $this->tenant);

    $this->patch($this->tenant->url("users/{$user->id}/deactivate"))->assertSessionHas('status');
    expect($user->fresh()->is_active)->toBeFalse();

    $this->patch($this->tenant->url("users/{$user->id}/activate"))->assertSessionHas('status');
    expect($user->fresh()->is_active)->toBeTrue();
});

it('does not let admins deactivate themselves', function () {
    $this->patch($this->tenant->url("users/{$this->admin->id}/deactivate"))->assertForbidden();

    expect($this->admin->fresh()->is_active)->toBeTrue();
});

it('returns 404 for another tenant\'s user', function (string $method, string $path) {
    $outsider = userWithRole(UserRole::Technician);

    $this->{$method}($this->tenant->url(sprintf($path, $outsider->id)), ['name' => 'x', 'role' => 'technician'])
        ->assertNotFound();
})->with([
    'edit' => ['get', 'users/%d/edit'],
    'update' => ['put', 'users/%d'],
    'deactivate' => ['patch', 'users/%d/deactivate'],
    'resend invite' => ['post', 'users/%d/invitation'],
]);

it('lets supervisors view but not edit users', function () {
    actingAsRole(UserRole::Supervisor, $this->tenant);
    $user = userWithRole(UserRole::Technician, $this->tenant);

    $this->get($this->tenant->url('users'))->assertOk();
    $this->get($this->tenant->url("users/{$user->id}/edit"))->assertForbidden();
});

it('keeps technicians and client users out of user management', function (UserRole $role) {
    actingAsRole($role, $this->tenant);

    $this->get($this->tenant->url('users'))->assertForbidden();
})->with([UserRole::Technician, UserRole::ClientUser]);
