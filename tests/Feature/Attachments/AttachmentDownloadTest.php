<?php

use App\Enums\WorkOrderStatus;
use App\Models\Attachment;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    Storage::fake('local');
    $this->w = workOrderWorld();

    $this->workOrder = WorkOrder::factory()->forSite($this->w->site)->assignedTo($this->w->technician, WorkOrderStatus::InProgress)->create();
    $this->attachment = Attachment::factory()->forWorkOrder($this->workOrder)->create(['thumbnail_path' => 'thumbs/t.jpg']);
    Storage::disk('local')->put($this->attachment->path, 'full-size-bytes');
    Storage::disk('local')->put('thumbs/t.jpg', 'thumbnail-bytes');
});

function signedAttachmentUrl(Attachment $attachment, array $extra = [], int $minutes = 30): string
{
    return URL::temporarySignedRoute('attachments.show', now()->addMinutes($minutes), ['attachment' => $attachment->id, ...$extra]);
}

it('serves the file to someone who can see the work order', function () {
    $response = $this->actingAs($this->w->technician)->get($this->attachment->url());

    $response->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($response->streamedContent())->toBe('full-size-bytes');
});

it('serves the thumbnail when asked', function () {
    $response = $this->actingAs($this->w->clientUser)->get($this->attachment->url(thumbnail: true));

    expect($response->streamedContent())->toBe('thumbnail-bytes');
});

it('rejects unsigned, tampered and expired links', function () {
    $this->actingAs($this->w->technician);

    $this->get($this->w->tenant->url("attachments/{$this->attachment->id}"))->assertForbidden();
    $this->get(signedAttachmentUrl($this->attachment).'x')->assertForbidden();

    $url = signedAttachmentUrl($this->attachment, minutes: 1);
    $this->travel(2)->minutes();
    $this->get($url)->assertForbidden();
});

it('does not let a valid link be reused by someone who cannot see the work order', function () {
    $url = $this->attachment->url();

    $this->actingAs($this->w->otherTechnician)->get($url)->assertForbidden();
});

it('requires a signed-in user', function () {
    $this->get($this->attachment->url())->assertRedirect($this->w->tenant->url('login'));
});

it('returns 404 for another tenant\'s attachment', function () {
    $foreign = Attachment::factory()->create();

    $this->actingAs($this->w->admin)->get(signedAttachmentUrl($foreign))->assertNotFound();
});
