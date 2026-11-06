<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Support\TenantRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        $asset = $this->route('asset');

        return $asset instanceof Asset
            ? $this->user()?->can('update', $asset) ?? false
            : $this->user()?->can('create', Asset::class) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $asset = $this->route('asset');

        return [
            'site_id' => ['required', 'integer', TenantRules::exists('sites')->withoutTrashed()],
            // The location must sit inside the chosen site.
            'location_id' => ['required', 'integer', TenantRules::exists('locations')->where('site_id', $this->integer('site_id'))->withoutTrashed()],
            'category_id' => ['required', 'integer', TenantRules::exists('categories')],
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Z0-9][A-Z0-9\/-]*$/', TenantRules::unique('assets', 'code', $asset instanceof Asset ? $asset : null)],
            'make' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'serial_no' => ['nullable', 'string', 'max:100'],
            'installed_at' => ['nullable', 'date', 'before_or_equal:today'],
            'warranty_until' => ['nullable', 'date', 'after_or_equal:installed_at'],
            'status' => ['required', Rule::enum(AssetStatus::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'The asset code may only contain letters, numbers, "-" and "/".',
            'location_id.exists' => 'The location must belong to the selected site.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => strtoupper(trim((string) $this->input('code')))]);
    }
}
