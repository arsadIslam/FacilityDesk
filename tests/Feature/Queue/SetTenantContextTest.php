<?php

use App\Jobs\Middleware\SetTenantContext;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;

it('runs the job inside the tenant it was dispatched for', function () {
    $tenant = Tenant::factory()->create();
    User::factory()->forTenant($tenant)->create();
    User::factory()->create();

    $seen = (new SetTenantContext($tenant->id))->handle(new stdClass, fn () => [
        'tenant' => app(TenantContext::class)->id(),
        'team' => getPermissionsTeamId(),
        'users' => User::count(),
    ]);

    expect($seen)->toBe(['tenant' => $tenant->id, 'team' => $tenant->id, 'users' => 1])
        ->and(app(TenantContext::class)->check())->toBeFalse();
});

it('restores the previous context after the job', function () {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();
    app(TenantContext::class)->set($a);

    (new SetTenantContext($b->id))->handle(new stdClass, fn () => null);

    expect(app(TenantContext::class)->id())->toBe($a->id);
});

it('deletes the job when the tenant no longer exists', function () {
    $job = new class
    {
        public bool $deleted = false;

        public bool $ran = false;

        public function delete(): void
        {
            $this->deleted = true;
        }
    };

    (new SetTenantContext(999_999))->handle($job, function ($job) {
        $job->ran = true;
    });

    expect($job->deleted)->toBeTrue()->and($job->ran)->toBeFalse();
});
