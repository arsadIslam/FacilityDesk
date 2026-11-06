<?php

declare(strict_types=1);

namespace App\Actions\Attachments;

use App\Enums\AttachmentKind;
use App\Jobs\ProcessUploadedPhoto;
use App\Models\Attachment;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Stores an already-validated upload against a work order and queues photo
 * processing. Files go to the private attachments disk under a random name;
 * the client's file name is kept only for display.
 */
final class StoreAttachment
{
    /**
     * @throws AuthorizationException
     */
    public function handle(WorkOrder $workOrder, UploadedFile $file, User $uploader, AttachmentKind $kind = AttachmentKind::General): Attachment
    {
        // Before/after photos are part of doing the work; general photos can
        // be added by anyone involved (e.g. the requester when raising it).
        Gate::forUser($uploader)->authorize($kind === AttachmentKind::General ? 'view' : 'upload', $workOrder);

        $disk = (string) config('filesystems.attachments_disk');
        $extension = $file->extension();
        $directory = "tenants/{$workOrder->tenant_id}/work-orders/{$workOrder->id}";

        $path = $file->storeAs($directory, strtolower((string) Str::ulid()).'.'.$extension, $disk);

        if ($path === false) {
            throw new RuntimeException('The file could not be stored.');
        }

        $attachment = new Attachment;
        $attachment->forceFill([
            'tenant_id' => $workOrder->tenant_id,
            'uploaded_by' => $uploader->id,
            'kind' => $kind,
            'disk' => $disk,
            'path' => $path,
            'original_name' => Str::limit(basename($file->getClientOriginalName()), 200, ''),
            'mime_type' => (string) $file->getMimeType(),
            'size_bytes' => (int) $file->getSize(),
        ]);
        $workOrder->attachments()->save($attachment);

        if ($attachment->isImage()) {
            ProcessUploadedPhoto::dispatch($attachment->id, $attachment->tenant_id);
        } else {
            $attachment->forceFill(['processed_at' => now()])->save();
        }

        return $attachment;
    }
}
