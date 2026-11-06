<?php

use App\Enums\AttachmentKind;
use App\Enums\WorkOrderStatus;
use App\Jobs\ProcessUploadedPhoto;
use App\Livewire\WorkOrders\Create;
use App\Livewire\WorkOrders\Show;
use App\Models\Attachment;
use App\Models\WorkOrder;
use App\Support\AttachmentRules;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
    Queue::fake([ProcessUploadedPhoto::class]);
    $this->w = workOrderWorld();
});

function raiseWithPhotos(stdClass $w, array $photos)
{
    return Livewire::actingAs($w->clientUser)->test(Create::class)
        ->set('location_id', (string) $w->location->id)
        ->set('category_id', (string) $w->category->id)
        ->set('title', 'Broken window')
        ->set('photos', $photos)
        ->call('save');
}

it('stores photos raised with a work order on the private disk and queues processing', function () {
    raiseWithPhotos($this->w, [
        UploadedFile::fake()->image('front.jpg', 800, 600),
        UploadedFile::fake()->create('quote.pdf', 200, 'application/pdf'),
    ])->assertHasNoErrors();

    $workOrder = WorkOrder::sole();
    $attachments = $workOrder->attachments()->orderBy('id')->get();

    expect($attachments)->toHaveCount(2)
        ->and($attachments[0])
        ->kind->toBe(AttachmentKind::General)
        ->disk->toBe('local')
        ->original_name->toBe('front.jpg')
        ->tenant_id->toBe($this->w->tenant->id)
        ->uploaded_by->toBe($this->w->clientUser->id)
        ->and($attachments[0]->path)->toStartWith("tenants/{$this->w->tenant->id}/work-orders/{$workOrder->id}/")
        ->and($attachments[0]->path)->not->toContain('front')
        ->and($attachments[1]->mime_type)->toBe('application/pdf')
        ->and($attachments[1]->processed_at)->not->toBeNull();

    Storage::disk('local')->assertExists($attachments->pluck('path')->all());
    expect(Storage::disk('public')->allFiles())->toBe([]);

    Queue::assertPushedOn('media', ProcessUploadedPhoto::class, fn ($job) => $job->attachmentId === $attachments[0]->id && $job->tenantId === $this->w->tenant->id);
    Queue::assertPushed(ProcessUploadedPhoto::class, 1);
});

it('rejects disallowed file types', function (UploadedFile $file) {
    raiseWithPhotos($this->w, [$file])->assertHasErrors('photos.0');

    expect(WorkOrder::count())->toBe(0);
})->with([
    'gif' => fn () => UploadedFile::fake()->image('anim.gif'),
    'svg' => fn () => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'),
    'html' => fn () => UploadedFile::fake()->create('page.html', 10, 'text/html'),
]);

// Livewire::test() reports a fake upload's MIME type from its extension, so
// content sniffing is checked directly against a real file on disk.
it('rejects a script renamed to .jpg by sniffing its contents', function () {
    $path = tempnam(sys_get_temp_dir(), 'upload');
    file_put_contents($path, '<?php echo "pwned";');
    $file = new UploadedFile($path, 'shell.jpg', 'image/jpeg', null, true);

    $validator = Validator::make(['photo' => $file], ['photo' => AttachmentRules::file()]);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('photo'))->toContain('must be a file of type');

    unlink($path);
});

it('accepts a real JPEG through the same rules', function () {
    $file = UploadedFile::fake()->image('real.jpg');

    expect(Validator::make(['photo' => $file], ['photo' => AttachmentRules::file()])->passes())->toBeTrue();
});

it('rejects files over 5 MB', function () {
    raiseWithPhotos($this->w, [UploadedFile::fake()->image('huge.jpg')->size(5121)])->assertHasErrors('photos.0');
});

it('allows at most five photos when raising a work order', function () {
    raiseWithPhotos($this->w, array_map(fn ($i) => UploadedFile::fake()->image("p{$i}.jpg"), range(1, 6)))
        ->assertHasErrors('photos');
});

it('lets the assigned technician upload before and after photos', function () {
    $wo = WorkOrder::factory()->forSite($this->w->site)->assignedTo($this->w->technician, WorkOrderStatus::InProgress)->create();

    Livewire::actingAs($this->w->technician)->test(Show::class, ['workOrder' => $wo])
        ->set('photo_kind', 'after')
        ->set('photo', UploadedFile::fake()->image('done.png'))
        ->call('uploadPhoto')
        ->assertHasNoErrors()
        ->assertSet('flash', 'Photo uploaded.');

    expect(Attachment::sole())->kind->toBe(AttachmentKind::After)->mime_type->toBe('image/png');
});

it('does not let other users upload before/after photos', function (string $who) {
    $wo = WorkOrder::factory()->forSite($this->w->site)->assignedTo($this->w->technician, WorkOrderStatus::InProgress)->create();

    Livewire::actingAs($this->w->{$who})->test(Show::class, ['workOrder' => $wo])
        ->assertDontSee('Upload')
        ->set('photo', UploadedFile::fake()->image('x.jpg'))
        ->call('uploadPhoto')
        ->assertForbidden();

    expect(Attachment::count())->toBe(0);
})->with(['clientUser']);

it('does not accept "general" through the before/after form', function () {
    $wo = WorkOrder::factory()->forSite($this->w->site)->assignedTo($this->w->technician, WorkOrderStatus::InProgress)->create();

    Livewire::actingAs($this->w->technician)->test(Show::class, ['workOrder' => $wo])
        ->set('photo_kind', 'general')
        ->set('photo', UploadedFile::fake()->image('x.jpg'))
        ->call('uploadPhoto')
        ->assertHasErrors('photo_kind');
});

it('stops uploads once the work order is finished', function () {
    $wo = WorkOrder::factory()->forSite($this->w->site)->assignedTo($this->w->technician, WorkOrderStatus::Closed)->create();

    expect($this->w->technician->can('upload', $wo))->toBeFalse();
});
