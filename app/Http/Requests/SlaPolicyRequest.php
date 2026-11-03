<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\SlaPolicy;
use Illuminate\Foundation\Http\FormRequest;

final class SlaPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var SlaPolicy $policy */
        $policy = $this->route('slaPolicy');

        return $this->user()?->can('update', $policy) ?? false;
    }

    /**
     * Up to one year, in minutes.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'response_minutes' => ['required', 'integer', 'min:1', 'max:525600'],
            'resolution_minutes' => ['required', 'integer', 'max:525600', 'gt:response_minutes'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['resolution_minutes.gt' => 'The resolution time must be longer than the response time.'];
    }
}
