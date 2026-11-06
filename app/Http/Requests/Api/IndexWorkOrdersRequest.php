<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Enums\Priority;
use App\Enums\WorkOrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexWorkOrdersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(WorkOrderStatus::class)],
            'priority' => ['nullable', Rule::enum(Priority::class)],
            'site_id' => ['nullable', 'integer'],
            'assigned_to' => ['nullable', 'integer'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function queryParameters(): array
    {
        return [
            'status' => ['description' => 'One of open, assigned, in_progress, on_hold, resolved, closed, cancelled.', 'example' => 'open'],
            'priority' => ['description' => 'One of critical, high, medium, low.', 'example' => 'high'],
            'site_id' => ['example' => '1'],
            'assigned_to' => ['description' => 'Technician user id.', 'example' => '7'],
            'from' => ['description' => 'Raised on or after this date (tenant timezone).', 'example' => '2026-05-01'],
            'to' => ['description' => 'Raised on or before this date (tenant timezone).', 'example' => '2026-05-31'],
            'per_page' => ['description' => '1-100, default 20.', 'example' => '20'],
        ];
    }
}
