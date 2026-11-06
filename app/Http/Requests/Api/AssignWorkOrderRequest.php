<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Models\WorkOrder;
use App\Support\TenantRules;
use Illuminate\Foundation\Http\FormRequest;

final class AssignWorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var WorkOrder $workOrder */
        $workOrder = $this->route('workOrder');

        return $this->user()?->can('assign', $workOrder) ?? false;
    }

    /**
     * Whether the user is an active technician is checked by AssignWorkOrder.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'assignee_id' => ['required', 'integer', TenantRules::exists('users')->withoutTrashed()],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function bodyParameters(): array
    {
        return [
            'assignee_id' => ['description' => 'An active technician of the workspace.', 'example' => 7],
            'note' => ['example' => 'Closest to site today.'],
        ];
    }
}
