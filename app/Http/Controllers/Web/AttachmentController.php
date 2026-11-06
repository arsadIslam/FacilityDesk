<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves attachments from the private disk. The route is "signed", so links
 * expire; the policy check means a leaked link is still useless to anyone
 * who cannot see the work order.
 */
final class AttachmentController extends Controller
{
    public function show(Request $request, Attachment $attachment): StreamedResponse|RedirectResponse
    {
        Gate::authorize('view', $attachment);

        $path = $request->boolean('thumb') && $attachment->thumbnail_path !== null
            ? $attachment->thumbnail_path
            : $attachment->path;

        $disk = Storage::disk($attachment->disk);

        // Object storage: hand the browser a short-lived presigned URL instead
        // of streaming the bytes through PHP.
        if (config("filesystems.disks.{$attachment->disk}.driver") === 's3') {
            return redirect()->away($disk->temporaryUrl($path, now()->addMinutes(5)));
        }

        return $disk->response($path, $attachment->original_name, [
            'Content-Type' => $attachment->mime_type,
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ], $attachment->isImage() ? 'inline' : 'attachment');
    }
}
