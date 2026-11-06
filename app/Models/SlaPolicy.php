<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Priority;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\SlaPolicyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property Priority $priority
 * @property int $response_minutes
 * @property int $resolution_minutes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class SlaPolicy extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<SlaPolicyFactory> */
    use HasFactory;

    /**
     * priority is fixed per row (one row per priority per tenant); only the targets change.
     *
     * @var list<string>
     */
    protected $fillable = [
        'response_minutes',
        'resolution_minutes',
    ];

    /**
     * The current tenant's targets for $priority, falling back to the
     * platform defaults if the tenant's row is missing.
     *
     * @return array{response: int, resolution: int}
     */
    public static function targetsFor(Priority $priority): array
    {
        $policy = static::query()->where('priority', $priority->value)->first();

        return $policy === null
            ? $priority->defaultSla()
            : ['response' => $policy->response_minutes, 'resolution' => $policy->resolution_minutes];
    }

    protected function casts(): array
    {
        return [
            'priority' => Priority::class,
            'response_minutes' => 'integer',
            'resolution_minutes' => 'integer',
        ];
    }
}
