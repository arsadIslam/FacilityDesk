<?php

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\User;
use App\Notifications\UserInvitation;
use App\Services\TenantContext;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();

    $this->tenant = tenantWithRoles(['name' => 'Acme Facilities']);
    $this->admin = actingAsRole(UserRole::TenantAdmin, $this->tenant);
});

function inviteUrl(User $invitee): string
{
    $url = null;
    Notification::assertSentTo($invitee, UserInvitation::class, function (UserInvitation $notification) use (&$url) {
        $url = $notification->url;

        return true;
    });

    return $url;
}

function invitee(string $email): User
{
    return User::withoutGlobalScopes()->where('email', $email)->sole();
}

it('invites a user with a role', function () {
    $this->post($this->tenant->url('users'), [
        'name' => 'Tom Tech',
        'email' => 'Tom@Example.test',
        'role' => UserRole::Technician->value,
    ])->assertRedirect($this->tenant->url('users'));

    $tom = invitee('tom@example.test');

    expect($tom->tenant_id)->toBe($this->tenant->id)
        ->and($tom->hasPendingInvitation())->toBeTrue()
        ->and(app(TenantContext::class)->run($this->tenant, fn () => $tom->fresh()->role()))->toBe(UserRole::Technician)
        ->and(inviteUrl($tom))->toStartWith($this->tenant->url('invitations/'.$tom->id));
});

it('requires a client for client users and stores it', function () {
    $client = Client::factory()->forTenant($this->tenant)->create();

    $this->post($this->tenant->url('users'), ['name' => 'Cara', 'email' => 'cara@client.test', 'role' => 'client_user'])
        ->assertSessionHasErrors('client_id');

    $this->post($this->tenant->url('users'), ['name' => 'Cara', 'email' => 'cara@client.test', 'role' => 'client_user', 'client_id' => $client->id])
        ->assertSessionHasNoErrors();

    expect(invitee('cara@client.test')->client_id)->toBe($client->id);
});

it('rejects a client from another tenant', function () {
    $foreignClient = Client::factory()->create();

    $this->post($this->tenant->url('users'), ['name' => 'Cara', 'email' => 'cara@client.test', 'role' => 'client_user', 'client_id' => $foreignClient->id])
        ->assertSessionHasErrors('client_id');
});

it('rejects an email already used in this tenant but allows it in another', function () {
    $this->post($this->tenant->url('users'), ['name' => 'Dup', 'email' => $this->admin->email, 'role' => 'technician'])
        ->assertSessionHasErrors('email');

    $outsider = userWithRole(UserRole::Technician);

    $this->post($this->tenant->url('users'), ['name' => 'Same Email', 'email' => $outsider->email, 'role' => 'technician'])
        ->assertSessionHasNoErrors();
});

it('cannot invite a super admin', function () {
    $this->post($this->tenant->url('users'), ['name' => 'Root', 'email' => 'root@example.test', 'role' => 'super_admin'])
        ->assertSessionHasErrors('role');
});

it('forbids supervisors from inviting users', function () {
    actingAsRole(UserRole::Supervisor, $this->tenant);

    $this->post($this->tenant->url('users'), ['name' => 'Tom', 'email' => 'tom@example.test', 'role' => 'technician'])
        ->assertForbidden();
});

it('lets the invitee set a password and signs them in', function () {
    $this->post($this->tenant->url('users'), ['name' => 'Tom', 'email' => 'tom@example.test', 'role' => 'technician']);
    $tom = invitee('tom@example.test');
    $url = inviteUrl($tom);

    auth()->logout();

    $this->get($url)->assertOk()->assertSee('Welcome, Tom');

    $this->post($url, ['password' => 'Tom-Passw0rd!', 'password_confirmation' => 'Tom-Passw0rd!'])
        ->assertRedirect($this->tenant->url());

    $tom->refresh();
    expect(Hash::check('Tom-Passw0rd!', $tom->password))->toBeTrue()
        ->and($tom->hasVerifiedEmail())->toBeTrue();
    $this->assertAuthenticatedAs($tom);
});

it('does not accept an invitation twice', function () {
    $this->post($this->tenant->url('users'), ['name' => 'Tom', 'email' => 'tom@example.test', 'role' => 'technician']);
    $url = inviteUrl(invitee('tom@example.test'));
    auth()->logout();

    $this->post($url, ['password' => 'Tom-Passw0rd!', 'password_confirmation' => 'Tom-Passw0rd!']);
    auth()->logout();

    $this->post($url, ['password' => 'Hijack-Passw0rd!', 'password_confirmation' => 'Hijack-Passw0rd!'])
        ->assertRedirect($this->tenant->url('login'));

    expect(Hash::check('Tom-Passw0rd!', invitee('tom@example.test')->password))->toBeTrue();
});

it('rejects tampered, expired and cross-tenant invitation links', function () {
    $this->post($this->tenant->url('users'), ['name' => 'Tom', 'email' => 'tom@example.test', 'role' => 'technician']);
    $url = inviteUrl(invitee('tom@example.test'));
    auth()->logout();

    $this->get($url.'x')->assertForbidden();

    // On another subdomain the user does not exist at all: 404, like any cross-tenant access.
    $otherTenant = tenantWithRoles();
    $this->get(str_replace($this->tenant->slug.'.', $otherTenant->slug.'.', $url))->assertNotFound();

    $this->travel(config('tenancy.invitation_expiry_hours') + 1)->hours();
    $this->get($url)->assertForbidden();
});

it('resends a pending invitation', function () {
    $this->post($this->tenant->url('users'), ['name' => 'Tom', 'email' => 'tom@example.test', 'role' => 'technician']);
    $tom = invitee('tom@example.test');

    $this->post($this->tenant->url("users/{$tom->id}/invitation"))->assertSessionHas('status');

    Notification::assertSentToTimes($tom, UserInvitation::class, 2);
});
