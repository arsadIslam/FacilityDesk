<?php

use App\Enums\UserRole;
use App\Models\Asset;
use App\Models\Client;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\TenantContext;
use Illuminate\Support\Facades\Hash;

it('seeds two workspaces with role-wise logins and realistic data', function () {
    $this->artisan('demo:seed')->expectsOutputToContain('Demo ready')->assertSuccessful();

    $acme = Tenant::where('slug', 'acme')->sole();
    expect(Tenant::count())->toBe(2)
        ->and(Client::withoutGlobalScopes()->count())->toBe(5)
        ->and(Site::withoutGlobalScopes()->count())->toBe(12)
        ->and(Asset::withoutGlobalScopes()->whereNull('qr_token')->count())->toBe(0)
        ->and(WorkOrder::withoutGlobalScopes()->count())->toBe(200);

    $statuses = WorkOrder::withoutGlobalScopes()->distinct()->pluck('status')->map->value->sort()->values()->all();
    expect($statuses)->toBe(['assigned', 'cancelled', 'closed', 'in_progress', 'on_hold', 'open', 'resolved']);

    $breached = WorkOrder::withoutGlobalScopes()->where(fn ($q) => $q->where('is_response_breached', true)->orWhere('is_resolution_breached', true))->count();
    expect($breached)->toBeGreaterThan(0)->toBeLessThan(100);

    app(TenantContext::class)->run($acme, function () {
        foreach (['admin' => UserRole::TenantAdmin, 'supervisor' => UserRole::Supervisor, 'tech' => UserRole::Technician, 'client' => UserRole::ClientUser] as $local => $role) {
            $user = User::where('email', "{$local}@acme.test")->sole();
            expect($user->role())->toBe($role)
                ->and(Hash::check('password', $user->password))->toBeTrue()
                ->and($user->hasVerifiedEmail())->toBeTrue();
        }
    });
});

it('refuses to seed twice without --fresh', function () {
    $this->artisan('demo:seed');

    $this->artisan('demo:seed')->expectsOutputToContain('already exist')->assertFailed();
});

it('benchmarks the seeded data', function () {
    $this->artisan('demo:seed');

    $this->artisan('app:benchmark', ['--runs' => 1])
        ->expectsOutputToContain('List: newest, page 1')
        ->expectsOutputToContain('Dashboard: this month (uncached)')
        ->assertSuccessful();
});
