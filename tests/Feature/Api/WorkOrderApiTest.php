<?php

use App\Enums\Priority;
use App\Enums\WorkOrderSource;
use App\Enums\WorkOrderStatus;
use App\Jobs\ProcessUploadedPhoto;
use App\Models\Attachment;
use App\Models\WorkOrder;
use App\Models\WorkOrderComment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake('local');
    Queue::fake([ProcessUploadedPhoto::class]);
    $this->w = workOrderWorld();
});

function newWorkOrderPayload(stdClass $w, array $overrides = []): array
{
    return [
        'site_id' => $w->site->id,
        'location_id' => $w->location->id,
        'category_id' => $w->category->id,
        'title' => 'Lift stuck on floor 3',
        'priority' => 'critical',
        ...$overrides,
    ];
}

describe('list', function () {
    it('returns a paginated, visibility-scoped list with meta', function () {
        WorkOrder::factory()->forSite($this->w->site)->count(3)->create();
        WorkOrder::factory()->create(); // another tenant
        Sanctum::actingAs($this->w->supervisor);

        $this->getJson(api('work-orders?per_page=2'))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonStructure(['data' => [['id', 'number', 'status', 'priority', 'site' => ['id', 'name'], 'location' => ['path'], 'sla' => ['response_due_at', 'resolution_due_at', 'is_resolution_breached']]], 'links' => ['first', 'next'], 'meta']);
    });

    it('shows technicians only their jobs', function () {
        $mine = WorkOrder::factory()->forSite($this->w->site)->assignedTo($this->w->technician)->create();
        WorkOrder::factory()->forSite($this->w->site)->create();
        Sanctum::actingAs($this->w->technician);

        $this->getJson(api('work-orders'))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mine->id);
    });

    it('filters by status, priority, site, assignee and date', function () {
        $hit = WorkOrder::factory()->forSite($this->w->site)->assignedTo($this->w->technician)->create(['priority' => Priority::High, 'created_at' => '2026-05-10 06:00:00']);
        WorkOrder::factory()->forSite($this->w->site)->create(['priority' => Priority::High, 'created_at' => '2026-05-10 06:00:00']);
        WorkOrder::factory()->forSite($this->w->site)->assignedTo($this->w->technician)->create(['priority' => Priority::Low, 'created_at' => '2026-05-10 06:00:00']);
        WorkOrder::factory()->forSite($this->w->site)->assignedTo($this->w->technician)->create(['priority' => Priority::High, 'created_at' => '2026-04-01 06:00:00']);
        Sanctum::actingAs($this->w->supervisor);

        $query = http_build_query(['status' => 'assigned', 'priority' => 'high', 'site_id' => $this->w->site->id, 'assigned_to' => $this->w->technician->id, 'from' => '2026-05-01', 'to' => '2026-05-31']);

        $this->getJson(api("work-orders?{$query}"))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $hit->id);
    });

    it('rejects invalid filters with the standard 422 shape', function () {
        Sanctum::actingAs($this->w->supervisor);

        $this->getJson(api('work-orders?status=broken&per_page=500'))
            ->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors' => ['status', 'per_page']]);
    });
});

describe('create', function () {
    it('raises a work order with photos via multipart', function () {
        Sanctum::actingAs($this->w->supervisor);

        $response = $this->post(api('work-orders'), newWorkOrderPayload($this->w, [
            'photos' => [UploadedFile::fake()->image('lift.jpg'), UploadedFile::fake()->create('quote.pdf', 50, 'application/pdf')],
        ]), ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.priority', 'critical')
            ->assertJsonPath('data.source', 'api')
            ->assertJsonPath('data.site.id', $this->w->site->id);

        $wo = WorkOrder::sole();
        expect($wo->source)->toBe(WorkOrderSource::Api)
            ->and($wo->attachments()->count())->toBe(2);
        Queue::assertPushed(ProcessUploadedPhoto::class, 1);
    });

    it('returns the 422 shape for invalid input', function () {
        Sanctum::actingAs($this->w->supervisor);

        $this->postJson(api('work-orders'), ['site_id' => $this->w->site->id])
            ->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors' => ['location_id', 'category_id', 'title']]);
    });

    it('rejects another tenant\'s site and disallowed files', function () {
        Sanctum::actingAs($this->w->supervisor);
        $foreign = WorkOrder::factory()->create();

        $this->postJson(api('work-orders'), newWorkOrderPayload($this->w, ['site_id' => $foreign->site_id]))->assertJsonValidationErrors('site_id');
        $this->post(api('work-orders'), newWorkOrderPayload($this->w, ['photos' => [UploadedFile::fake()->create('x.svg', 5, 'image/svg+xml')]]), ['Accept' => 'application/json'])
            ->assertJsonValidationErrors('photos.0');
    });

    it('ignores the priority sent by a client user', function () {
        Sanctum::actingAs($this->w->clientUser);

        $this->postJson(api('work-orders'), newWorkOrderPayload($this->w))->assertCreated()->assertJsonPath('data.priority', 'high');
    });

    it('does not let technicians raise work orders', function () {
        Sanctum::actingAs($this->w->technician);

        $this->postJson(api('work-orders'), newWorkOrderPayload($this->w))->assertForbidden()->assertJsonStructure(['message']);
    });
});

describe('detail', function () {
    it('includes history, comments, attachments and the caller\'s actions', function () {
        $wo = WorkOrder::factory()->forSite($this->w->site)->assignedTo($this->w->technician)->create();
        $wo->recordStatus(null, WorkOrderStatus::Open, $this->w->supervisor);
        WorkOrderComment::factory()->create(['work_order_id' => $wo->id, 'body' => 'Public']);
        WorkOrderComment::factory()->internal()->create(['work_order_id' => $wo->id, 'body' => 'Internal only']);
        Attachment::factory()->forWorkOrder($wo)->create();
        Sanctum::actingAs($this->w->technician);

        $this->getJson(api("work-orders/{$wo->id}"))
            ->assertOk()
            ->assertJsonPath('data.status_history.0.to', 'open')
            ->assertJsonCount(2, 'data.comments')
            ->assertJsonPath('data.attachments.0.url', route('api.v1.attachments.show', Attachment::sole()->id))
            ->assertJsonPath('data.available_transitions.statuses', ['in_progress'])
            ->assertJsonPath('data.available_transitions.can_assign', false)
            ->assertJsonPath('data.available_transitions.can_upload', true);
    });

    it('never returns internal comments to client users', function () {
        $wo = WorkOrder::factory()->forSite($this->w->site)->create();
        WorkOrderComment::factory()->create(['work_order_id' => $wo->id, 'body' => 'Public']);
        WorkOrderComment::factory()->internal()->create(['work_order_id' => $wo->id, 'body' => 'Internal only']);
        Sanctum::actingAs($this->w->clientUser);

        $response = $this->getJson(api("work-orders/{$wo->id}"))->assertOk()->assertJsonCount(1, 'data.comments');

        expect($response->getContent())->not->toContain('Internal only');
    });

    it('forbids a work order the caller may not see in their own tenant', function () {
        $wo = WorkOrder::factory()->forSite($this->w->site)->create();
        Sanctum::actingAs($this->w->technician);

        $this->getJson(api("work-orders/{$wo->id}"))->assertForbidden();
    });
});

describe('actions', function () {
    it('lets supervisors assign and technicians work', function () {
        $wo = WorkOrder::factory()->forSite($this->w->site)->create();

        Sanctum::actingAs($this->w->supervisor);
        $this->patchJson(api("work-orders/{$wo->id}/assign"), ['assignee_id' => $this->w->technician->id])
            ->assertOk()->assertJsonPath('data.status', 'assigned')->assertJsonPath('data.assignee.id', $this->w->technician->id);

        Sanctum::actingAs($this->w->technician);
        $this->patchJson(api("work-orders/{$wo->id}/status"), ['status' => 'in_progress'])->assertOk()->assertJsonPath('data.status', 'in_progress');
        $this->patchJson(api("work-orders/{$wo->id}/status"), ['status' => 'resolved'])->assertUnprocessable()->assertJsonValidationErrors('note');
        $this->patchJson(api("work-orders/{$wo->id}/status"), ['status' => 'resolved', 'note' => 'Reset the controller'])->assertOk();

        Sanctum::actingAs($this->w->clientUser);
        $this->patchJson(api("work-orders/{$wo->id}/status"), ['status' => 'closed'])->assertOk()->assertJsonPath('data.status', 'closed');
    });

    it('does not let client users or technicians assign', function (string $who) {
        $wo = WorkOrder::factory()->forSite($this->w->site)->create();
        Sanctum::actingAs($this->w->{$who});

        $this->patchJson(api("work-orders/{$wo->id}/assign"), ['assignee_id' => $this->w->technician->id])->assertForbidden();
    })->with(['clientUser', 'technician']);

    it('does not let a technician change someone else\'s job', function () {
        $wo = WorkOrder::factory()->forSite($this->w->site)->assignedTo($this->w->otherTechnician)->create();
        Sanctum::actingAs($this->w->technician);

        $this->patchJson(api("work-orders/{$wo->id}/status"), ['status' => 'in_progress'])->assertForbidden();
    });

    it('answers 422 with errors.status for an illegal transition', function () {
        $wo = WorkOrder::factory()->forSite($this->w->site)->create();
        Sanctum::actingAs($this->w->admin);

        $this->patchJson(api("work-orders/{$wo->id}/status"), ['status' => 'resolved', 'note' => 'x'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    });

    it('adds comments, internal ones for staff only', function () {
        $wo = WorkOrder::factory()->forSite($this->w->site)->assignedTo($this->w->technician)->create();

        Sanctum::actingAs($this->w->technician);
        $this->postJson(api("work-orders/{$wo->id}/comments"), ['body' => 'Parts ordered', 'is_internal' => true])
            ->assertCreated()->assertJsonPath('data.is_internal', true)->assertJsonPath('data.author.id', $this->w->technician->id);

        Sanctum::actingAs($this->w->clientUser);
        $this->postJson(api("work-orders/{$wo->id}/comments"), ['body' => 'Sneaky', 'is_internal' => true])->assertForbidden();
        $this->postJson(api("work-orders/{$wo->id}/comments"), ['body' => ''])->assertJsonValidationErrors('body');
    });

    it('uploads before/after photos for the assigned technician', function () {
        $wo = WorkOrder::factory()->forSite($this->w->site)->assignedTo($this->w->technician, WorkOrderStatus::InProgress)->create();

        Sanctum::actingAs($this->w->technician);
        $this->post(api("work-orders/{$wo->id}/media"), ['kind' => 'before', 'file' => UploadedFile::fake()->image('b.jpg')], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('data.kind', 'before')->assertJsonPath('data.processed', false);

        Sanctum::actingAs($this->w->clientUser);
        $this->post(api("work-orders/{$wo->id}/media"), ['kind' => 'after', 'file' => UploadedFile::fake()->image('a.jpg')], ['Accept' => 'application/json'])
            ->assertForbidden();
    });

    it('downloads an attachment with the bearer token', function () {
        $wo = WorkOrder::factory()->forSite($this->w->site)->assignedTo($this->w->technician)->create();
        $attachment = Attachment::factory()->forWorkOrder($wo)->create();
        Storage::disk('local')->put($attachment->path, 'jpeg-bytes');

        Sanctum::actingAs($this->w->technician);
        expect($this->get(api("attachments/{$attachment->id}"))->assertOk()->streamedContent())->toBe('jpeg-bytes');

        Sanctum::actingAs($this->w->otherTechnician);
        $this->getJson(api("attachments/{$attachment->id}"))->assertForbidden();
    });
});
