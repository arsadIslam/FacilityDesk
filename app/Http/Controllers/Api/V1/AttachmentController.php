<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Group('Work orders')]
#[Authenticated]
final class AttachmentController extends Controller
{
    /**
     * Download an attachment
     *
     * Streams the file (or redirects to a 5-minute presigned URL on S3) if the
     * caller can see the work order.
     */
    #[QueryParam('thumb', 'boolean', 'Return the 320px thumbnail instead.', required: false, example: false)]
    public function show(Request $request, Attachment $attachment): StreamedResponse|RedirectResponse
    {
        Gate::authorize('view', $attachment);

        $path = $request->boolean('thumb') && $attachment->thumbnail_path !== null ? $attachment->thumbnail_path : $attachment->path;
        $disk = Storage::disk($attachment->disk);

        if (config("filesystems.disks.{$attachment->disk}.driver") === 's3') {
            return redirect()->away($disk->temporaryUrl($path, now()->addMinutes(5)));
        }

        return $disk->response($path, $attachment->original_name, [
            'Content-Type' => $attachment->mime_type,
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
