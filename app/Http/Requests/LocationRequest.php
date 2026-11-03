<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\LocationType;
use App\Models\Site;
use App\Support\TenantRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class LocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->site()) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(LocationType::class)],
            'name' => ['required', 'string', 'max:120', 'not_regex:/\//'],
            'parent_id' => [
                'nullable',
                'integer',
                TenantRules::exists('locations')->where('site_id', $this->site()->id)->withoutTrashed(),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.not_regex' => 'The name may not contain "/".',
            'parent_id.exists' => 'The parent location must belong to this site.',
        ];
    }

    public function site(): Site
    {
        /** @var Site */
        return $this->route('site');
    }

    public function type(): LocationType
    {
        return $this->enum('type', LocationType::class) ?? LocationType::Area;
    }

    public function parentId(): ?int
    {
        return $this->filled('parent_id') ? $this->integer('parent_id') : null;
    }
}
