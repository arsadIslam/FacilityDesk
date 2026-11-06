<?php

use App\Enums\TenantStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;

beforeEach(function () {
    $this->tenant = tenantWithRoles(['slug' => 'acme', 'name' => 'Acme Facilities']);
    $this->user = userWithRole(UserRole::Technician, $this->tenant, ['email' => 'tom@acme.test']);
});

function login(array $overrides = []): TestResponse
{
    return test()->postJson(api('auth/login'), [
        'tenant' => 'acme',
        'email' => 'tom@acme.test',
        'password' => 'password',
        'device_name' => 'Pixel 8',
        ...$overrides,
    ]);
}

it('returns a bearer token for valid credentials', function () {
    $response = login()->assertOk()
        ->assertJsonStructure(['token', 'token_type', 'expires_at', 'user' => ['id', 'name', 'email', 'role']])
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('user.role', 'technician');

    expect(PersonalAccessToken::findToken($response->json('token'))->tokenable->is($this->user))->toBeTrue()
        ->and(PersonalAccessToken::sole()->name)->toBe('Pixel 8')
        ->and(PersonalAccessToken::sole()->expires_at)->not->toBeNull();
});

it('uses the token to call the API', function () {
    $token = login()->json('token');

    $this->withToken($token)->getJson(api('me'))
        ->assertOk()
        ->assertJsonPath('data.email', 'tom@acme.test')
        ->assertJsonPath('data.tenant.slug', 'acme')
        ->assertJsonPath('data.permissions', ['assets.view', 'work_orders.internal_comments', 'work_orders.work']);
});

it('gives the same 422 for a wrong password, unknown email or unknown workspace', function (array $overrides) {
    login($overrides)
        ->assertUnprocessable()
        ->assertExactJson([
            'message' => __('auth.failed'),
            'errors' => ['email' => [__('auth.failed')]],
        ]);
})->with([
    'wrong password' => [['password' => 'nope']],
    'unknown email' => [['email' => 'nobody@acme.test']],
    'unknown workspace' => [['tenant' => 'globex']],
]);

it('does not log in a user from another workspace with the same email', function () {
    $other = tenantWithRoles(['slug' => 'globex']);
    userWithRole(UserRole::Technician, $other, ['email' => 'tom@acme.test', 'password' => 'different-password']);

    login(['tenant' => 'globex'])->assertUnprocessable();
    login(['tenant' => 'acme'])->assertOk();
});

it('validates the request shape', function () {
    $this->postJson(api('auth/login'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['tenant', 'email', 'password', 'device_name']);
});

it('refuses deactivated, unverified and suspended accounts', function () {
    $this->user->update(['is_active' => false]);
    login()->assertUnprocessable();

    $this->user->update(['is_active' => true]);
    $this->user->forceFill(['email_verified_at' => null])->save();
    login()->assertForbidden()->assertJsonPath('message', 'Verify your email address before using the API.');

    $this->user->forceFill(['email_verified_at' => now()])->save();
    $this->tenant->forceFill(['status' => TenantStatus::Suspended])->save();
    login()->assertForbidden()->assertJsonPath('message', 'This workspace has been suspended. Please contact support.');
});

it('limits login to 5 attempts a minute per IP', function () {
    foreach (range(1, 5) as $attempt) {
        login(['password' => 'wrong'])->assertUnprocessable();
    }

    login()->assertTooManyRequests();
});

it('revokes only the current token on logout', function () {
    $phone = login()->json('token');
    $tablet = login(['device_name' => 'iPad'])->json('token');

    $this->withToken($phone)->postJson(api('auth/logout'))->assertNoContent();

    expect(PersonalAccessToken::count())->toBe(1);
    app('auth')->forgetGuards();
    $this->withToken($phone)->getJson(api('me'))->assertUnauthorized();
    app('auth')->forgetGuards();
    $this->withToken($tablet)->getJson(api('me'))->assertOk();
});

it('answers 401 JSON without a token, even without an Accept header', function () {
    $this->getJson(api('me'))->assertUnauthorized()->assertExactJson(['message' => 'Unauthenticated.']);
    $this->get(api('work-orders'))->assertUnauthorized()->assertJson(['message' => 'Unauthenticated.']);
});

it('rejects expired tokens', function () {
    $token = login()->json('token');

    $this->travel(31)->days();

    $this->withToken($token)->getJson(api('me'))->assertUnauthorized();
});

it('stops a deactivated user\'s existing token', function () {
    $token = login()->json('token');
    $this->user->update(['is_active' => false]);

    $this->withToken($token)->getJson(api('me'))->assertForbidden()->assertJsonPath('message', 'Your account has been deactivated.');
});

it('does not serve the API on tenant subdomains', function () {
    $this->postJson($this->tenant->url('api/v1/auth/login'), ['tenant' => 'acme'])->assertNotFound();
});

it('limits API calls to 60 a minute per token', function () {
    $token = User::query()->find($this->user->id)->createToken('test')->plainTextToken;

    foreach (range(1, 60) as $i) {
        $this->withToken($token)->getJson(api('me'))->assertOk();
    }

    $this->withToken($token)->getJson(api('me'))->assertTooManyRequests();
});
