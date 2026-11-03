<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\Site;
use App\Rules\UserHasRole;
use Illuminate\Foundation\Http\FormRequest;

final class SiteCoverageRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Site $site */
        $site = $this->route('site');

        return $this->user()?->can('update', $site) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'user_ids' => ['array', new UserHasRole(...UserRole::fieldRoles())],
            'user_ids.*' => ['integer', 'distinct'],
        ];
    }

    /**
     * @return list<int>
     */
    public function userIds(): array
    {
        return array_values(array_map('intval', (array) $this->input('user_ids', [])));
    }
}
