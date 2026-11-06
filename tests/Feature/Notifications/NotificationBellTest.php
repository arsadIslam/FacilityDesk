<?php

use App\Livewire\NotificationBell;
use App\Models\WorkOrder;
use App\Notifications\WorkOrderAssigned;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    $this->w = workOrderWorld();
    $this->wo = WorkOrder::factory()->forSite($this->w->site)->create();
});

it('shows the unread count and latest notifications', function () {
    $this->w->technician->notify(new WorkOrderAssigned($this->wo));
    $this->w->technician->notify(new WorkOrderAssigned($this->wo));

    Livewire::actingAs($this->w->technician)->test(NotificationBell::class)
        ->assertSet('unreadCount', 2)
        ->assertSee("{$this->wo->number} was assigned to you");
});

it('marks a notification read and opens its work order', function () {
    $this->w->technician->notify(new WorkOrderAssigned($this->wo));
    $notification = $this->w->technician->notifications()->sole();

    Livewire::actingAs($this->w->technician)->test(NotificationBell::class)
        ->call('open', $notification->id)
        ->assertRedirect("/work-orders/{$this->wo->id}");

    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('marks everything read', function () {
    $this->w->technician->notify(new WorkOrderAssigned($this->wo));
    $this->w->technician->notify(new WorkOrderAssigned($this->wo));

    Livewire::actingAs($this->w->technician)->test(NotificationBell::class)
        ->call('markAllRead')
        ->assertSet('unreadCount', 0);
});

it('cannot open someone else\'s notification', function () {
    $this->w->supervisor->notify(new WorkOrderAssigned($this->wo));
    $theirs = $this->w->supervisor->notifications()->sole();

    expect(fn () => Livewire::actingAs($this->w->technician)->test(NotificationBell::class)->call('open', $theirs->id))
        ->toThrow(ModelNotFoundException::class);

    expect($theirs->fresh()->read_at)->toBeNull();
});

it('never redirects off-site, whatever is stored in the notification', function () {
    $this->w->technician->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'x',
        'data' => ['message' => 'Hi', 'path' => '//evil.example/phish'],
        'tenant_id' => $this->w->tenant->id,
    ]);

    Livewire::actingAs($this->w->technician)->test(NotificationBell::class)
        ->call('open', $this->w->technician->notifications()->sole()->id)
        ->assertRedirect('/');
});

it('is shown in the app header', function () {
    $this->actingAs($this->w->technician)
        ->get($this->w->tenant->url())
        ->assertSeeLivewire(NotificationBell::class);
});
