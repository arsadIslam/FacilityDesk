<?php

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();

    $this->tenant = tenantWithRoles();
    $this->user = userWithRole(UserRole::Technician, $this->tenant, ['email' => 'tara@example.test']);
});

function requestResetToken(Tenant $tenant, User $user): string
{
    test()->post($tenant->url('forgot-password'), ['email' => $user->email]);

    $token = null;
    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token) {
        $token = $notification->token;

        return true;
    });

    return $token;
}

it('sends a reset link that points at the tenant subdomain', function () {
    $this->post($this->tenant->url('forgot-password'), ['email' => 'tara@example.test'])
        ->assertSessionHas('status');

    Notification::assertSentTo($this->user, ResetPassword::class, function (ResetPassword $notification) {
        $url = $notification->toMail($this->user)->actionUrl;

        return str_starts_with($url, $this->tenant->url('reset-password/'));
    });
});

it('answers the same way for an unknown email', function () {
    $this->post($this->tenant->url('forgot-password'), ['email' => 'nobody@example.test'])
        ->assertSessionHas('status', 'If that address has an account, we have emailed a password reset link.');

    Notification::assertNothingSent();
});

it('resets the password with a valid token', function () {
    $token = requestResetToken($this->tenant, $this->user);

    $this->post($this->tenant->url('reset-password'), [
        'token' => $token,
        'email' => 'tara@example.test',
        'password' => 'N3w-Passw0rd!',
        'password_confirmation' => 'N3w-Passw0rd!',
    ])->assertRedirect($this->tenant->url('login'));

    expect(Hash::check('N3w-Passw0rd!', $this->user->fresh()->password))->toBeTrue();
});

it('does not let a token from one tenant reset the same email in another', function () {
    $otherTenant = tenantWithRoles();
    $twin = userWithRole(UserRole::Technician, $otherTenant, ['email' => 'tara@example.test']);

    $tokenForOther = requestResetToken($otherTenant, $twin);

    $this->post($this->tenant->url('reset-password'), [
        'token' => $tokenForOther,
        'email' => 'tara@example.test',
        'password' => 'N3w-Passw0rd!',
        'password_confirmation' => 'N3w-Passw0rd!',
    ])->assertSessionHasErrors('email');

    expect(Hash::check('password', $this->user->fresh()->password))->toBeTrue();
});

it('keeps separate tokens when the same email resets in two tenants', function () {
    $otherTenant = tenantWithRoles();
    $twin = userWithRole(UserRole::Technician, $otherTenant, ['email' => 'tara@example.test']);

    $tokenA = requestResetToken($this->tenant, $this->user);
    requestResetToken($otherTenant, $twin);

    // Requesting a reset on tenant B must not invalidate tenant A's token.
    $this->post($this->tenant->url('reset-password'), [
        'token' => $tokenA,
        'email' => 'tara@example.test',
        'password' => 'N3w-Passw0rd!',
        'password_confirmation' => 'N3w-Passw0rd!',
    ])->assertSessionHasNoErrors();

    expect(Hash::check('N3w-Passw0rd!', $this->user->fresh()->password))->toBeTrue()
        ->and(Hash::check('password', $twin->fresh()->password))->toBeTrue();
});
