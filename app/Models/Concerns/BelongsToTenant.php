<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Tenant;
use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Scopes every query to the current tenant and stamps tenant_id on create.
 *
 * With no tenant in context (Super Admin, cross-tenant console commands) the
 * scope is not applied; those code paths must filter explicitly.
 *
 * @mixin Model
 */
trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $query): void {
            $tenantId = app(TenantContext::class)->id();

            if ($tenantId !== null) {
                $query->where($query->getModel()->qualifyColumn('tenant_id'), $tenantId);
            }
        });

        static::creating(function (Model $model): void {
            if ($model->getAttribute('tenant_id') === null) {
                $model->setAttribute('tenant_id', app(TenantContext::class)->id());
            }
        });
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
