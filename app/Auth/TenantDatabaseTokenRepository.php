<?php

declare(strict_types=1);

namespace App\Auth;

use App\Services\TenantContext;
use Illuminate\Auth\Passwords\DatabaseTokenRepository;
use Illuminate\Database\Query\Builder;

/**
 * Password reset tokens keyed by (tenant_id, email) instead of email alone.
 *
 * The same address can be registered in several tenants; without the tenant
 * key a token issued on tenant B would also reset that address on tenant A.
 */
final class TenantDatabaseTokenRepository extends DatabaseTokenRepository
{
    protected function getTable(): Builder
    {
        return parent::getTable()->where('tenant_id', $this->tenantId());
    }

    /**
     * @return array<string, mixed>
     */
    protected function getPayload($email, #[\SensitiveParameter] $token): array
    {
        return ['tenant_id' => $this->tenantId(), ...parent::getPayload($email, $token)];
    }

    private function tenantId(): ?int
    {
        return app(TenantContext::class)->id();
    }
}
