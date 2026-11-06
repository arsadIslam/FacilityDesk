<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Attachments\StoreAttachment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreMediaRequest;
use App\Http\Resources\AttachmentResource;
use App\Models\Attachment;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;

#[Group('Work orders')]
#[Authenticated]
final class WorkOrderMediaController extends Controller
{
    /**
     * Upload a photo
     *
     * The assigned technician, or a supervisor/admin, while the work order is
     * still open. Photos are resized and stripped of EXIF in the background,
     * so `processed` is false at first.
     */
    #[ResponseFromApiResource(AttachmentResource::class, Attachment::class, status: 201)]
    public function store(StoreMediaRequest $request, WorkOrder $workOrder, StoreAttachment $storeAttachment): JsonResponse
    {
        /** @var User $uploader */
        $uploader = $request->user();
        /** @var UploadedFile $file */
        $file = $request->file('file');

        $attachment = $storeAttachment->handle($workOrder, $file, $uploader, $request->kind());

        return (new AttachmentResource($attachment->load('uploader')))->response()->setStatusCode(201);
    }
}
