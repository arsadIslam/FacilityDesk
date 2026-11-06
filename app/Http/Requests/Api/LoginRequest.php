<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

final class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'tenant' => ['required', 'string', 'max:60'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function bodyParameters(): array
    {
        return [
            'tenant' => ['description' => 'The workspace slug (the subdomain).', 'example' => 'acme'],
            'email' => ['example' => 'tech@acme.test'],
            'password' => ['example' => 'password'],
            'device_name' => ['description' => 'Shown in the user\'s list of signed-in devices.', 'example' => 'Pixel 8'],
        ];
    }
}
