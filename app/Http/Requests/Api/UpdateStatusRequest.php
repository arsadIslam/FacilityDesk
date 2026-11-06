<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Enums\WorkOrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Only shape is checked here. Whether this user may make this move, and
 * whether the move is legal, is ChangeStatus's job (policy + state machine).
 */
final class UpdateStatusRequest extends FormRequest
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
            'status' => ['required', Rule::enum(WorkOrderStatus::class)],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function status(): WorkOrderStatus
    {
        return $this->enum('status', WorkOrderStatus::class) ?? WorkOrderStatus::Open;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function bodyParameters(): array
    {
        return [
            'status' => ['description' => 'Target status. "assigned" here means reopen (from resolved); use /assign to assign.', 'example' => 'in_progress'],
            'note' => ['description' => 'Required to resolve, reopen or cancel.', 'example' => 'Replaced the capacitor.'],
        ];
    }
}
