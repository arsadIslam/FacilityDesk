<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

/**
 * Validation rules pinned to the current tenant.
 *
 * Laravel's exists/unique rules query the table directly and ignore Eloquent
 * global scopes, so without this a form could reference another tenant's row.
 */
final class TenantRules
{
    public static function exists(string $table, string $column = 'id'): Exists
    {
        return Rule::exists($table, $column)->where('tenant_id', self::tenantId());
    }

    public static function unique(string $table, string $column, ?Model $ignore = null): Unique
    {
        $rule = Rule::unique($table, $column)->where('tenant_id', self::tenantId());

        return $ignore === null ? $rule : $rule->ignore($ignore);
    }

    private static function tenantId(): ?int
    {
        return app(TenantContext::class)->id();
    }
}
