<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Jobs\Middleware\SetTenantContext;
use App\Models\Attachment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\EncodedImageInterface;
use Intervention\Image\Interfaces\ImageInterface;
use Throwable;

/**
 * Rotates a photo upright, caps it at 1600px, strips EXIF (GPS position,
 * device details) by re-encoding, and writes a 320px square thumbnail.
 *
 * Carries ids only, so the payload stays small and the tenant is restored
 * by SetTenantContext before anything is queried.
 */
final class ProcessUploadedPhoto implements ShouldQueue
{
    use Queueable;

    public const MAX_DIMENSION = 1600;

    public const THUMBNAIL_SIZE = 320;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public int $timeout = 60;

    public function __construct(
        public readonly int $attachmentId,
        public readonly int $tenantId,
    ) {
        $this->onQueue('media');
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new SetTenantContext($this->tenantId)];
    }

    public function handle(): void
    {
        $attachment = Attachment::query()->find($this->attachmentId);

        if ($attachment === null || ! $attachment->isImage()) {
            return;
        }

        $disk = Storage::disk($attachment->disk);

        // GD re-encodes from raw pixels, so no metadata survives; reading
        // applies the EXIF orientation first so the photo stays upright.
        $image = ImageManager::gd(autoOrientation: true)->read($disk->get($attachment->path) ?? '');
        $image->scaleDown(self::MAX_DIMENSION, self::MAX_DIMENSION);
        $thumbnail = (clone $image)->coverDown(self::THUMBNAIL_SIZE, self::THUMBNAIL_SIZE);

        $photo = $this->encode($image, $attachment->mime_type);
        $thumbnailPath = dirname($attachment->path).'/thumbs/'.basename($attachment->path);

        $disk->put($attachment->path, (string) $photo);
        $disk->put($thumbnailPath, (string) $this->encode($thumbnail, $attachment->mime_type));

        $attachment->forceFill([
            'thumbnail_path' => $thumbnailPath,
            'size_bytes' => $photo->size(),
            'processed_at' => now(),
        ])->save();
    }

    public function failed(Throwable $exception): void
    {
        // The original stays available; only the resize and thumbnail are missing.
        Log::warning('Photo processing failed', [
            'attachment_id' => $this->attachmentId,
            'tenant_id' => $this->tenantId,
            'error' => $exception->getMessage(),
        ]);
    }

    private function encode(ImageInterface $image, string $mimeType): EncodedImageInterface
    {
        return match ($mimeType) {
            'image/png' => $image->toPng(),
            'image/webp' => $image->toWebp(quality: 82),
            default => $image->toJpeg(quality: 82),
        };
    }
}
