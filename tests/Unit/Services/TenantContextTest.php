<?php

use App\Models\Tenant;
use App\Services\TenantContext;

function makeTenant(int $id): Tenant
{
    return (new Tenant)->forceFill(['id' => $id, 'slug' => "tenant-{$id}"]);
}

it('is empty by default', function () {
    $context = new TenantContext;

    expect($context->check())->toBeFalse()
        ->and($context->id())->toBeNull()
        ->and($context->tenant())->toBeNull();
});

it('stores and clears the current tenant', function () {
    $context = new TenantContext;
    $tenant = makeTenant(7);

    $context->set($tenant);

    expect($context->check())->toBeTrue()
        ->and($context->id())->toBe(7)
        ->and($context->tenant())->toBe($tenant);

    $context->clear();

    expect($context->id())->toBeNull();
});

it('runs a callback as another tenant and restores the previous one', function () {
    $context = new TenantContext;
    $context->set(makeTenant(1));

    $seen = $context->run(makeTenant(2), fn (Tenant $tenant) => $context->id());

    expect($seen)->toBe(2)
        ->and($context->id())->toBe(1);
});

it('restores the previous tenant even when the callback throws', function () {
    $context = new TenantContext;

    expect(fn () => $context->run(makeTenant(2), fn () => throw new RuntimeException('boom')))
        ->toThrow(RuntimeException::class);

    expect($context->check())->toBeFalse();
});

it('is registered as a scoped binding', function () {
    $first = app(TenantContext::class);
    $first->set(makeTenant(3));

    expect(app(TenantContext::class))->toBe($first);

    app()->forgetScopedInstances();

    expect(app(TenantContext::class))->not->toBe($first)
        ->and(app(TenantContext::class)->check())->toBeFalse();
});
