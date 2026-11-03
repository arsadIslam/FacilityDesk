<?php

use App\Enums\TenantPlan;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

function signupUrl(string $path = '/signup'): string
{
    return 'http://'.config('tenancy.domain').$path;
}

/**
 * @return array<string, string>
 */
function validSignup(array $overrides = []): array
{
    return array_merge([
        'company_name' => 'Acme Facilities',
        'slug' => 'acme',
        'admin_name' => 'Asha Rao',
        'admin_email' => 'asha@acme.test',
        'password' => 'Str0ng-Passw0rd!',
        'password_confirmation' => 'Str0ng-Passw0rd!',
    ], $overrides);
}

it('shows the signup form', function () {
    $this->get(signupUrl())
        ->assertOk()
        ->assertSee('Create your workspace')
        ->assertSee('.'.config('tenancy.domain'));
});

it('creates a tenant and its admin user', function () {
    $this->travelTo(now()->startOfDay());

    $this->post(signupUrl(), validSignup())
        ->assertRedirect(signupUrl('/signup/success'));

    $tenant = Tenant::where('slug', 'acme')->sole();

    expect($tenant)
        ->name->toBe('Acme Facilities')
        ->email->toBe('asha@acme.test')
        ->plan->toBe(TenantPlan::Trial)
        ->status->toBe(TenantStatus::Active)
        ->and($tenant->trial_ends_at->equalTo(now()->addDays(14)))->toBeTrue();

    $admin = User::where('email', 'asha@acme.test')->sole();

    expect($admin->tenant_id)->toBe($tenant->id)
        ->and($admin->name)->toBe('Asha Rao')
        ->and(Hash::check('Str0ng-Passw0rd!', $admin->password))->toBeTrue();
});

it('links to the new workspace after signup', function () {
    $this->followingRedirects()
        ->post(signupUrl(), validSignup())
        ->assertOk()
        ->assertSee('Acme Facilities is ready')
        ->assertSee(Tenant::where('slug', 'acme')->sole()->url());
});

it('redirects the success page back to the form when there is no new workspace', function () {
    $this->get(signupUrl('/signup/success'))->assertRedirect(signupUrl());
});

it('normalises the slug and email', function () {
    $this->post(signupUrl(), validSignup(['slug' => '  ACME ', 'admin_email' => 'Asha@ACME.test']))
        ->assertSessionHasNoErrors();

    expect(Tenant::where('slug', 'acme')->exists())->toBeTrue()
        ->and(User::where('email', 'asha@acme.test')->exists())->toBeTrue();
});

it('ignores platform-controlled fields sent by the client', function () {
    $this->post(signupUrl(), validSignup([
        'status' => 'suspended',
        'plan' => 'pro',
        'tenant_id' => 999,
    ]))->assertSessionHasNoErrors();

    expect(Tenant::sole())
        ->status->toBe(TenantStatus::Active)
        ->plan->toBe(TenantPlan::Trial);
});

it('rejects invalid input', function (array $overrides, string $field) {
    Tenant::factory()->create(['slug' => 'taken']);

    $this->post(signupUrl(), validSignup($overrides))
        ->assertSessionHasErrors($field);

    expect(Tenant::count())->toBe(1);
})->with([
    'missing company' => [['company_name' => ''], 'company_name'],
    'slug taken' => [['slug' => 'taken'], 'slug'],
    'slug reserved' => [['slug' => 'www'], 'slug'],
    'slug too short' => [['slug' => 'ab'], 'slug'],
    'slug with spaces' => [['slug' => 'ac me'], 'slug'],
    'slug with underscore' => [['slug' => 'ac_me'], 'slug'],
    'slug leading hyphen' => [['slug' => '-acme'], 'slug'],
    'slug trailing hyphen' => [['slug' => 'acme-'], 'slug'],
    'invalid email' => [['admin_email' => 'not-an-email'], 'admin_email'],
    'missing name' => [['admin_name' => ''], 'admin_name'],
    'password mismatch' => [['password_confirmation' => 'different'], 'password'],
]);

it('rolls back the tenant when creating the admin fails', function () {
    User::creating(fn () => throw new RuntimeException('Simulated failure'));

    $this->withoutExceptionHandling();

    expect(fn () => $this->post(signupUrl(), validSignup()))
        ->toThrow(RuntimeException::class, 'Simulated failure');

    expect(Tenant::count())->toBe(0);
});

it('lets the new admin\'s workspace load on its subdomain', function () {
    $this->post(signupUrl(), validSignup());

    $this->get(Tenant::where('slug', 'acme')->sole()->url('login'))
        ->assertOk()
        ->assertSee('Sign in to Acme Facilities');
});
