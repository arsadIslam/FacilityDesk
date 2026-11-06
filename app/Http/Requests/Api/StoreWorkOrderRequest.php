<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Models\User;
use App\Models\WorkOrder;
use App\Support\AttachmentRules;
use App\Support\WorkOrderRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Same rules as the Livewire form (WorkOrderRules), plus photos[].
 */
final class StoreWorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', WorkOrder::class) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $user = $this->user();

        return [
            ...($user instanceof User ? WorkOrderRules::create($user, $this->integer('site_id') ?: null) : []),
            'photos' => ['array', 'max:'.AttachmentRules::MAX_PER_WORK_ORDER_CREATE],
            'photos.*' => AttachmentRules::file(),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function bodyParameters(): array
    {
        return [
            'site_id' => ['description' => 'A site from GET /sites.', 'example' => 1, 'required' => true],
            'location_id' => ['description' => 'A location of that site.', 'example' => 3, 'required' => true],
            'category_id' => ['example' => 2, 'required' => true],
            'asset_id' => ['description' => 'Optional asset at that site (e.g. from a QR scan).', 'example' => null],
            'priority' => ['description' => 'critical, high, medium or low. Ignored for client users; defaults to the category\'s.', 'example' => 'high'],
            'title' => ['example' => 'AC not cooling in meeting room 2', 'required' => true],
            'description' => ['example' => 'Blowing warm air since this morning.'],
            'photos' => ['description' => 'Up to 5 files: JPG, PNG, WebP or PDF, 5 MB each. Send as multipart/form-data.'],
        ];
    }
}
