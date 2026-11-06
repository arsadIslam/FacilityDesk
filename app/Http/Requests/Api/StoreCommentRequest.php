<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Models\WorkOrder;
use Illuminate\Foundation\Http\FormRequest;

final class StoreCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var WorkOrder $workOrder */
        $workOrder = $this->route('workOrder');

        return $this->user()?->can('comment', $workOrder) ?? false;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
            'is_internal' => ['boolean'],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function bodyParameters(): array
    {
        return [
            'body' => ['example' => 'Parts ordered, back tomorrow.'],
            'is_internal' => ['description' => 'Hidden from client users. Staff only.', 'example' => false],
        ];
    }
}
