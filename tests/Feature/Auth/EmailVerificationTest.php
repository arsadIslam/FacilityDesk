<?php

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

function verificationUrl(Tenant $tenant, User $user): string
{
    return URL::temporarySignedRoute('verification.verify', now()->addHour(), [
        'tenant' => $tenant->slug,
        'id' => $user->id,
        'hash' => sha1($user->email),
    ]);
}

it('emails the new tenant admin a verification link on their subdomain', function () {
    Notification::fake();

    $this->post('http://'.config('tenancy.domain').'/signup', [
        'company_name' => 'Acme Facilities',
        'slug' => 'acme',
        'admin_name' => 'Asha Rao',
        'admin_email' => 'asha@acme.test',
        'password' => 'Str0ng-Passw0rd!',
        'password_confirmation' => 'Str0ng-Passw0rd!',
    ]);

    $admin = User::where('email', 'asha@acme.test')->sole();
    $tenant = Tenant::where('slug', 'acme')->sole();

    expect($admin->hasVerifiedEmail())->toBeFalse();

    Notification::assertSentTo($admin, VerifyEmail::class, function (VerifyEmail $notification) use ($admin, $tenant) {
        return str_starts_with($notification->toMail($admin)->actionUrl, $tenant->url('verify-email/'));
    });
});

it('verifies the email from the signed link', function () {
    $tenant = tenantWithRoles();
    $user = userWithRole(UserRole::TenantAdmin, $tenant, ['email_verified_at' => null]);

    $this->actingAs($user)
        ->get(verificationUrl($tenant, $user))
        ->assertRedirect($tenant->url());

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('rejects a tampered verification link', function () {
    $tenant = tenantWithRoles();
    $user = userWithRole(UserRole::TenantAdmin, $tenant, ['email_verified_at' => null]);

    $this->actingAs($user)
        ->get(verificationUrl($tenant, $user).'tampered')
        ->assertForbidden();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('resends the verification email', function () {
    Notification::fake();
    $tenant = tenantWithRoles();
    $user = userWithRole(UserRole::TenantAdmin, $tenant, ['email_verified_at' => null]);

    $this->actingAs($user)
        ->post($tenant->url('email/verification-notification'))
        ->assertSessionHas('status');

    Notification::assertSentTo($user, VerifyEmail::class);
});
