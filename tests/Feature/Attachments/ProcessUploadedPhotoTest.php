<?php

use App\Jobs\ProcessUploadedPhoto;
use App\Models\Attachment;
use App\Models\Tenant;
use App\Models\WorkOrder;
use App\Services\TenantContext;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->workOrder = WorkOrder::factory()->create();
});

/**
 * A JPEG carrying an EXIF block with a recognisable string, inserted right
 * after the SOI marker, like a phone camera would.
 */
function jpegWithExif(int $width, int $height, string $secret): string
{
    $image = imagecreatetruecolor($width, $height);
    imagefilledrectangle($image, 0, 0, $width, $height, imagecolorallocate($image, 30, 120, 200));
    ob_start();
    imagejpeg($image, null, 90);
    $jpeg = (string) ob_get_clean();

    $text = $secret."\0";
    // Big-endian TIFF: one IFD entry, ImageDescription (0x010E), ASCII, value at offset 26.
    $tiff = 'MM'.pack('n', 42).pack('N', 8)
        .pack('n', 1)
        .pack('n', 0x010E).pack('n', 2).pack('N', strlen($text)).pack('N', 26)
        .pack('N', 0)
        .$text;
    $app1 = "Exif\0\0".$tiff;

    return substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($app1) + 2).$app1.substr($jpeg, 2);
}

function photoAttachment(WorkOrder $workOrder, string $bytes, string $mime = 'image/jpeg'): Attachment
{
    $attachment = Attachment::factory()->forWorkOrder($workOrder)->create(['mime_type' => $mime, 'size_bytes' => strlen($bytes)]);
    Storage::disk('local')->put($attachment->path, $bytes);

    return $attachment;
}

function runJob(Attachment $attachment): void
{
    $job = new ProcessUploadedPhoto($attachment->id, $attachment->tenant_id);
    $job->middleware()[0]->handle($job, fn ($job) => $job->handle());
}

it('caps the photo at 1600px and writes a square thumbnail', function () {
    $attachment = photoAttachment($this->workOrder, jpegWithExif(3000, 2000, 'x'));

    runJob($attachment);
    $attachment->refresh();

    [$width, $height] = getimagesizefromstring(Storage::disk('local')->get($attachment->path));
    [$thumbWidth, $thumbHeight] = getimagesizefromstring(Storage::disk('local')->get($attachment->thumbnail_path));

    expect([$width, $height])->toBe([1600, 1067])
        ->and([$thumbWidth, $thumbHeight])->toBe([320, 320])
        ->and($attachment->thumbnail_path)->toContain('/thumbs/')
        ->and($attachment->processed_at)->not->toBeNull()
        ->and($attachment->size_bytes)->toBe(strlen(Storage::disk('local')->get($attachment->path)));
});

it('strips EXIF metadata', function () {
    $original = jpegWithExif(400, 300, 'GPS 12.9716N 77.5946E');
    expect($original)->toContain('GPS 12.9716N');

    $attachment = photoAttachment($this->workOrder, $original);
    runJob($attachment);

    $processed = Storage::disk('local')->get($attachment->fresh()->path);

    expect($processed)->not->toContain('GPS 12.9716N')
        ->and($processed)->not->toContain("Exif\0\0");
});

it('does not upscale small photos', function () {
    $attachment = photoAttachment($this->workOrder, jpegWithExif(640, 480, 'x'));

    runJob($attachment);

    [$width, $height] = getimagesizefromstring(Storage::disk('local')->get($attachment->fresh()->path));

    expect([$width, $height])->toBe([640, 480]);
});

it('keeps PNG and WebP in their own format', function (string $mime, string $encoder) {
    $image = imagecreatetruecolor(50, 50);
    ob_start();
    $encoder($image);
    $attachment = photoAttachment($this->workOrder, (string) ob_get_clean(), $mime);

    runJob($attachment);

    expect(getimagesizefromstring(Storage::disk('local')->get($attachment->fresh()->path))['mime'])->toBe($mime);
})->with([
    'png' => ['image/png', 'imagepng'],
    'webp' => ['image/webp', 'imagewebp'],
]);

it('ignores PDFs', function () {
    $attachment = photoAttachment($this->workOrder, '%PDF-1.4 test', 'application/pdf');

    runJob($attachment);

    expect($attachment->fresh()->thumbnail_path)->toBeNull()
        ->and(Storage::disk('local')->get($attachment->path))->toBe('%PDF-1.4 test');
});

it('finds the attachment through the tenant scope inside its own tenant', function () {
    $attachment = photoAttachment($this->workOrder, jpegWithExif(100, 100, 'x'));

    // Another tenant in context, as on a busy worker: the middleware switches to the right one.
    app(TenantContext::class)->set(Tenant::factory()->create());

    runJob($attachment);

    expect($attachment->fresh()->processed_at)->not->toBeNull();
});

it('declares retries, backoff and the media queue', function () {
    $job = new ProcessUploadedPhoto(1, 1);

    expect($job->tries)->toBe(3)
        ->and($job->backoff)->toBe([10, 60])
        ->and($job->queue)->toBe('media');
});
