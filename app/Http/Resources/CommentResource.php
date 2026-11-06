<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\WorkOrderComment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Callers pass comments already filtered by WorkOrderComment::visibleTo(),
 * so internal ones never reach Client Users.
 *
 * @mixin WorkOrderComment
 */
final class CommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'is_internal' => $this->is_internal,
            'author' => new UserSummaryResource($this->whenLoaded('user')),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
