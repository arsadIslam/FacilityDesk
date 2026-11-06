<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Enums\AttachmentKind;
use App\Models\WorkOrder;
use App\Support\AttachmentRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var WorkOrder $workOrder */
        $workOrder = $this->route('workOrder');

        return $this->user()?->can('upload', $workOrder) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', ...AttachmentRules::file()],
            'kind' => ['required', Rule::enum(AttachmentKind::class)],
        ];
    }

    public function kind(): AttachmentKind
    {
        return $this->enum('kind', AttachmentKind::class) ?? AttachmentKind::General;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function bodyParameters(): array
    {
        return [
            'file' => ['description' => 'JPG, PNG, WebP or PDF, up to 5 MB.'],
            'kind' => ['description' => 'before, after or general.', 'example' => 'after'],
        ];
    }
}
