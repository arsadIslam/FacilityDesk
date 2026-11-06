<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Files are fetched from the authenticated API route with the same bearer
 * token; nothing is ever publicly reachable.
 *
 * @mixin Attachment
 */
final class AttachmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind->value,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'processed' => $this->processed_at !== null,
            'url' => route('api.v1.attachments.show', $this->id),
            'thumbnail_url' => $this->thumbnail_path ? route('api.v1.attachments.show', [$this->id, 'thumb' => 1]) : null,
            'uploaded_by' => new UserSummaryResource($this->whenLoaded('uploader')),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
