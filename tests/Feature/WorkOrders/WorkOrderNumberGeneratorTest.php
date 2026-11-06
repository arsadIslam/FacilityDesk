<?php

use App\Models\Tenant;
use App\Services\WorkOrderNumberGenerator;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->generator = app(WorkOrderNumberGenerator::class);
    $this->at = CarbonImmutable::parse('2026-06-15 10:00:00', 'UTC');
});

it('produces sequential numbers per tenant', function () {
    $tenant = Tenant::factory()->create();

    expect([
        $this->generator->next($tenant, $this->at),
        $this->generator->next($tenant, $this->at),
        $this->generator->next($tenant, $this->at),
    ])->toBe(['WO-2026-00001', 'WO-2026-00002', 'WO-2026-00003']);
});

it('keeps an independent sequence for each tenant', function () {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();

    $this->generator->next($a, $this->at);
    $this->generator->next($a, $this->at);

    expect($this->generator->next($b, $this->at))->toBe('WO-2026-00001')
        ->and($this->generator->next($a, $this->at))->toBe('WO-2026-00003');
});

it('restarts every year', function () {
    $tenant = Tenant::factory()->create();

    $this->generator->next($tenant, $this->at);

    expect($this->generator->next($tenant, $this->at->addYear()))->toBe('WO-2027-00001')
        ->and($this->generator->next($tenant, $this->at))->toBe('WO-2026-00002');
});

it('uses the year in the tenant\'s timezone', function () {
    $tenant = Tenant::factory()->create(['timezone' => 'Asia/Kolkata']);

    // 31 Dec 20:00 UTC is already 1 Jan 01:30 in India.
    expect($this->generator->next($tenant, CarbonImmutable::parse('2026-12-31 20:00:00', 'UTC')))->toBe('WO-2027-00001');
});

it('generates unique numbers over many calls', function () {
    $tenant = Tenant::factory()->create();

    $numbers = collect(range(1, 50))->map(fn () => $this->generator->next($tenant, $this->at));

    expect($numbers->unique())->toHaveCount(50)
        ->and($numbers->last())->toBe('WO-2026-00050');
});
