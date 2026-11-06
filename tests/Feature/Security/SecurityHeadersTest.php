<?php

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Tenant;
use Symfony\Component\Finder\Finder;

it('sends the baseline security headers on web pages', function () {
    $response = $this->get('http://'.config('tenancy.domain').'/signup');

    $response->assertOk()
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

    $csp = $response->headers->get('Content-Security-Policy');
    expect($csp)
        ->toContain("default-src 'self'")
        ->toContain("frame-ancestors 'none'")
        ->toContain("object-src 'none'")
        ->toContain("form-action 'self'")
        ->and($csp)->not->toMatch("/script-src[^;]*'unsafe-inline'/");
});

it('sends them on tenant pages, API responses and errors too', function () {
    $tenant = Tenant::factory()->create();

    $this->get($tenant->url('login'))->assertHeader('X-Frame-Options', 'DENY');
    $this->getJson(api('me'))->assertUnauthorized()->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->get('http://nobody.'.config('tenancy.domain').'/login')->assertNotFound()->assertHeader('X-Frame-Options', 'DENY');
});

it('only sends HSTS over HTTPS in production', function () {
    $this->get('http://'.config('tenancy.domain').'/')->assertHeaderMissing('Strict-Transport-Security');

    app()->detectEnvironment(fn () => 'production');

    $this->get('https://'.config('tenancy.domain').'/')
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

it('has no inline event handlers left in the views', function () {
    $offenders = collect(Finder::create()->files()->in(resource_path('views'))->name('*.blade.php'))
        ->filter(fn ($file) => preg_match('/\son(submit|click|change|load|input)=/i', $file->getContents()))
        ->map(fn ($file) => $file->getRelativePathname())
        ->values()
        ->all();

    expect($offenders)->toBe([]);
});

it('renders confirmation text as plain data, never script', function () {
    $tenant = tenantWithRoles();
    actingAsRole(UserRole::TenantAdmin, $tenant);
    Client::factory()->forTenant($tenant)->create(['name' => "x');alert(document.cookie);//"]);

    $this->get($tenant->url('clients'))
        ->assertOk()
        ->assertSee('data-confirm="Delete x&#039;);alert(document.cookie);//?"', false)
        ->assertDontSee('onsubmit', false);
});

it('keeps CORS closed unless an origin is configured', function () {
    $preflight = fn () => $this->call('OPTIONS', api('work-orders'), server: [
        'HTTP_ORIGIN' => 'https://evil.example',
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
    ]);

    expect($preflight()->headers->get('Access-Control-Allow-Origin'))->toBeNull();

    config(['cors.allowed_origins' => ['https://app.acme.test']]);
    $allowed = $this->call('OPTIONS', api('work-orders'), server: [
        'HTTP_ORIGIN' => 'https://app.acme.test',
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
    ]);

    expect($allowed->headers->get('Access-Control-Allow-Origin'))->toBe('https://app.acme.test');
});

it('defaults to debug off when APP_DEBUG is not set', function () {
    // Production must never show stack traces because a variable was forgotten.
    expect(file_get_contents(config_path('app.php')))->toContain("'debug' => (bool) env('APP_DEBUG', false)");
});
