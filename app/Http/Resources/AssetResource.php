<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Asset;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Asset
 */
final class AssetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'status' => $this->status->value,
            'make' => $this->make,
            'model' => $this->model,
            'serial_no' => $this->serial_no,
            'installed_at' => $this->installed_at?->toDateString(),
            'warranty_until' => $this->warranty_until?->toDateString(),
            'under_warranty' => $this->isUnderWarranty(),
            'site' => new SiteResource($this->whenLoaded('site')),
            'location' => new LocationResource($this->whenLoaded('location')),
            'category' => $this->whenLoaded('category', fn () => ['id' => $this->category->id, 'name' => $this->category->name]),
            'open_work_orders' => WorkOrderResource::collection($this->whenLoaded('workOrders')),
        ];
    }
}
