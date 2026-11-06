<?php

use App\Actions\WorkOrders\AssignWorkOrder;
use App\Actions\WorkOrders\ChangeStatus;
use App\Enums\WorkOrderStatus;
use App\Livewire\Audit\Index;
use App\Models\Activity;
use App\Models\WorkOrder;
use App\Services\TenantContext;
use Livewire\Livewire;

beforeEach(function () {
    $this->w = workOrderWorld();
    Activity::query()->withoutGlobalScope('tenant')->delete(); // drop setup noise
});

it('logs who changed what on a work order, tenant-stamped', function () {
    $this->actingAs($this->w->supervisor);
    $wo = WorkOrder::factory()->forSite($this->w->site)->create(['created_at' => now()]);

    app(AssignWorkOrder::class)->handle($wo, $this->w->technician, $this->w->supervisor);

    $entry = Activity::where('log_name', 'work_orders')->where('event', 'updated')->latest('id')->first();

    expect($entry)
        ->tenant_id->toBe($this->w->tenant->id)
        ->causer_id->toBe($this->w->supervisor->id)
        ->and($entry->properties['old'])->toMatchArray(['status' => 'open', 'assigned_to' => null])
        ->and($entry->properties['attributes'])->toMatchArray(['status' => 'assigned', 'assigned_to' => $this->w->technician->id]);
});

it('does not log system-maintained columns', function () {
    $wo = WorkOrder::factory()->forSite($this->w->site)->create();
    $before = Activity::count();

    $wo->forceFill(['is_resolution_breached' => true, 'breach_notified_at' => now(), 'hold_minutes' => 30])->save();

    expect(Activity::count())->toBe($before);
});

it('stamps the subject\'s tenant when written outside a request', function () {
    $wo = WorkOrder::factory()->forSite($this->w->site)->assignedTo($this->w->technician)->create();
    app(TenantContext::class)->clear(); // e.g. a queue worker

    $wo->update(['title' => 'Renamed by a job']);

    expect(Activity::withoutGlobalScope('tenant')->latest('id')->first()->tenant_id)->toBe($this->w->tenant->id);
});

it('logs role changes and user profile changes, never passwords', function () {
    $this->actingAs($this->w->admin)->put($this->w->tenant->url("users/{$this->w->technician->id}"), [
        'name' => 'Tina Renamed', 'role' => 'supervisor',
    ])->assertSessionHasNoErrors();
    // Fresh instance: Spatie diffs against the in-memory originals.
    $this->w->technician->fresh()->update(['password' => 'new-secret-password']);

    $role = Activity::where('log_name', 'roles')->sole();
    expect($role)
        ->event->toBe('role_changed')
        ->description->toBe('Role changed from Technician to Supervisor')
        ->causer_id->toBe($this->w->admin->id);

    $profile = Activity::where('log_name', 'users')->get();
    expect($profile)->toHaveCount(1)
        ->and($profile[0]->properties['attributes'])->toBe(['name' => 'Tina Renamed'])
        ->and(json_encode($profile))->not->toContain('new-secret-password');
});

it('shows tenant admins a filterable audit log', function () {
    $this->actingAs($this->w->supervisor);
    $wo = WorkOrder::factory()->forSite($this->w->site)->create();
    app(ChangeStatus::class)->handle($wo, WorkOrderStatus::Cancelled, $this->w->supervisor, 'Duplicate');

    Livewire::actingAs($this->w->admin)->test(Index::class)
        ->assertSee($wo->number)
        ->assertSee($this->w->supervisor->name)
        ->set('log', 'users')
        ->assertDontSee($wo->number);
});

it('never shows another tenant\'s entries', function () {
    $foreign = WorkOrder::factory()->create(['title' => 'Foreign job']);
    $foreign->update(['title' => 'Foreign job renamed']);

    Livewire::actingAs($this->w->admin)->test(Index::class)->assertDontSee($foreign->number);
});

it('is only for tenant admins', function (string $who) {
    $this->actingAs($this->w->{$who})->get($this->w->tenant->url('audit'))->assertForbidden();
})->with(['supervisor', 'technician', 'clientUser']);
