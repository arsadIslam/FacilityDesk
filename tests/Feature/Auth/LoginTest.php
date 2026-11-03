<?php

use App\Enums\UserRole;
use App\Http\Requests\LoginRequest;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->tenant = tenantWithRoles(['name' => 'Acme Facilities']);
    $this->user = userWithRole(UserRole::Supervisor, $this->tenant, ['email' => 'sam@acme.test']);
});

it('shows the login page for the tenant', function () {
    $this->get($this->tenant->url('login'))
        ->assertOk()
        ->assertSee('Sign in to Acme Facilities');
});

it('logs a user in on their own subdomain', function () {
    $this->post($this->tenant->url('login'), ['email' => 'sam@acme.test', 'password' => 'password'])
        ->assertRedirect('/');

    $this->assertAuthenticatedAs($this->user);
    expect($this->user->fresh()->last_login_at)->not->toBeNull();
});

it('rejects a wrong password', function () {
    $this->post($this->tenant->url('login'), ['email' => 'sam@acme.test', 'password' => 'wrong'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('does not let a user log in on another tenant\'s subdomain', function () {
    $other = tenantWithRoles();

    $this->post($other->url('login'), ['email' => 'sam@acme.test', 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('logs in the right account when the same email exists in two tenants', function () {
    $other = tenantWithRoles();
    $twin = userWithRole(UserRole::Technician, $other, ['email' => 'sam@acme.test']);

    $this->post($other->url('login'), ['email' => 'sam@acme.test', 'password' => 'password']);

    $this->assertAuthenticatedAs($twin);
});

it('does not let a deactivated user log in', function () {
    $this->user->update(['is_active' => false]);

    $this->post($this->tenant->url('login'), ['email' => 'sam@acme.test', 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('rate limits repeated failures', function () {
    foreach (range(1, LoginRequest::MAX_ATTEMPTS) as $attempt) {
        $this->post($this->tenant->url('login'), ['email' => 'sam@acme.test', 'password' => 'wrong']);
    }

    // Even the correct password is refused while locked out.
    $this->post($this->tenant->url('login'), ['email' => 'sam@acme.test', 'password' => 'password'])
        ->assertSessionHasErrors('email');

    expect(session('errors')->first('email'))->toContain('Too many login attempts');
    $this->assertGuest();
});

it('logs out', function () {
    $this->actingAs($this->user)
        ->post($this->tenant->url('logout'))
        ->assertRedirect($this->tenant->url('login'));

    $this->assertGuest();
});

it('ends the session of a user deactivated mid-session', function () {
    $this->actingAs($this->user);
    $this->user->update(['is_active' => false]);

    $this->get($this->tenant->url())
        ->assertRedirect($this->tenant->url('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('redirects signed-in users away from the login page', function () {
    $this->actingAs($this->user)
        ->get($this->tenant->url('login'))
        ->assertRedirect($this->tenant->url());
});

it('requires a verified email for the workspace', function () {
    $unverified = userWithRole(UserRole::Supervisor, $this->tenant, ['email_verified_at' => null]);

    $this->actingAs($unverified)
        ->get($this->tenant->url())
        ->assertRedirect($this->tenant->url('verify-email'));
});

it('shows the dashboard with the user\'s role', function () {
    $this->actingAs($this->user)
        ->get($this->tenant->url())
        ->assertOk()
        ->assertSee('Supervisor');
});

it('never treats a super admin as a tenant user', function () {
    $superAdmin = User::factory()->superAdmin()->create(['email' => 'root@platform.test']);

    $this->post(Tenant::factory()->create()->url('login'), ['email' => 'root@platform.test', 'password' => 'password'])
        ->assertSessionHasErrors('email');

    expect($superAdmin->isSuperAdmin())->toBeTrue();
    $this->assertGuest();
});
