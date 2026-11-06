<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\WorkOrderStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WorkOrderStatusHistory
 */
final class StatusHistoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'from' => $this->from_status?->value,
            'to' => $this->to_status->value,
            'note' => $this->note,
            'changed_by' => new UserSummaryResource($this->whenLoaded('changedBy')),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
