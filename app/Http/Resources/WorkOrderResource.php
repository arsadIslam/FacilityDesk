<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\WorkOrderStatus;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * Times are ISO 8601 in UTC; clients display them in site.timezone.
 *
 * @mixin WorkOrder
 */
final class WorkOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priority->value,
            'status' => $this->status->value,
            'source' => $this->source->value,
            'site' => new SiteResource($this->whenLoaded('site')),
            'location' => new LocationResource($this->whenLoaded('location')),
            'category' => $this->whenLoaded('category', fn () => ['id' => $this->category->id, 'name' => $this->category->name]),
            'asset' => $this->whenLoaded('asset', fn () => $this->asset === null ? null : ['id' => $this->asset->id, 'code' => $this->asset->code, 'name' => $this->asset->name]),
            'raised_by' => new UserSummaryResource($this->whenLoaded('raisedBy')),
            'assignee' => new UserSummaryResource($this->whenLoaded('assignee')),
            'sla' => [
                'response_due_at' => $this->response_due_at->toIso8601String(),
                'resolution_due_at' => $this->resolution_due_at->toIso8601String(),
                'responded_at' => $this->responded_at?->toIso8601String(),
                'resolved_at' => $this->resolved_at?->toIso8601String(),
                'closed_at' => $this->closed_at?->toIso8601String(),
                'hold_minutes' => $this->hold_minutes,
                'is_response_breached' => $this->is_response_breached,
                'is_resolution_breached' => $this->is_resolution_breached,
            ],
            'resolution_note' => $this->resolution_note,
            // Detail only (loaded by WorkOrderController::show()).
            'available_transitions' => $this->when($this->relationLoaded('statusHistories'), fn () => $this->availableTransitions($request)),
            'status_history' => StatusHistoryResource::collection($this->whenLoaded('statusHistories')),
            'comments' => CommentResource::collection($this->whenLoaded('comments')),
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }

    /**
     * What the caller may do next, so a mobile app can render the right buttons.
     * Assigning is listed separately because it needs a technician.
     *
     * @return array{statuses: list<string>, can_assign: bool, can_comment: bool, can_upload: bool}
     */
    private function availableTransitions(Request $request): array
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return ['statuses' => [], 'can_assign' => false, 'can_comment' => false, 'can_upload' => false];
        }

        $gate = Gate::forUser($user);
        $from = $this->status;

        return [
            'statuses' => collect($from->allowedTransitions())
                ->filter(fn (WorkOrderStatus $to) => ($to !== WorkOrderStatus::Assigned || $from === WorkOrderStatus::Resolved)
                    && $gate->allows('transition', [$this->resource, $to]))
                ->map(fn (WorkOrderStatus $to) => $to->value)
                ->values()
                ->all(),
            'can_assign' => $gate->allows('assign', $this->resource),
            'can_comment' => $gate->allows('comment', $this->resource),
            'can_upload' => $gate->allows('upload', $this->resource),
        ];
    }
}
