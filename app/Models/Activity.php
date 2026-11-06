<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

/**
 * Audit log entry, scoped to a tenant like every other tenant row.
 *
 * Entries written outside a request (queue workers, the SLA checker) have no
 * tenant in context, so the subject's tenant is used instead.
 *
 * @property int|null $tenant_id
 */
class Activity extends SpatieActivity
{
    use BelongsToTenant;

    /**
     * Spatie's model is fully unguarded; tenant_id only ever comes from
     * TenantContext or the subject (see booted()).
     *
     * @var list<string>
     */
    public $guarded = ['id', 'tenant_id'];

    protected static function booted(): void
    {
        static::creating(function (Activity $activity): void {
            if ($activity->tenant_id !== null || $activity->subject_type === null) {
                return;
            }

            $subject = $activity->subject;

            if ($subject instanceof Model && $subject->getAttribute('tenant_id') !== null) {
                $activity->tenant_id = (int) $subject->getAttribute('tenant_id');
            }
        });
    }
}
