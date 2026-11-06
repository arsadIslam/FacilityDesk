<?php

namespace Database\Factories;

use App\Enums\AttachmentKind;
use App\Models\Attachment;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Rows only: pair with Storage::fake() and put a file at "path" when the
 * bytes matter.
 *
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attachable_type' => 'work_order',
            'attachable_id' => WorkOrder::factory(),
            'tenant_id' => fn (array $a) => WorkOrder::query()->withoutGlobalScopes()->findOrFail($a['attachable_id'])->tenant_id,
            'uploaded_by' => fn (array $a) => WorkOrder::query()->withoutGlobalScopes()->findOrFail($a['attachable_id'])->raised_by,
            'kind' => AttachmentKind::General,
            'disk' => config('filesystems.attachments_disk'),
            'path' => fn (array $a) => "tenants/{$a['tenant_id']}/work-orders/{$a['attachable_id']}/".strtolower((string) Str::ulid()).'.jpg',
            'original_name' => 'photo.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1024,
        ];
    }

    public function forWorkOrder(WorkOrder $workOrder): static
    {
        return $this->state(fn () => [
            'attachable_type' => 'work_order',
            'attachable_id' => $workOrder->id,
            'tenant_id' => $workOrder->tenant_id,
            'uploaded_by' => $workOrder->raised_by,
        ]);
    }
}
