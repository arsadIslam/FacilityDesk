<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LocationType;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $site_id
 * @property int|null $parent_id
 * @property LocationType $type
 * @property string $name
 * @property string|null $path
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
class Location extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<LocationFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * site_id, parent_id and path are set by SaveLocation, never mass assigned.
     *
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'name',
    ];

    protected function casts(): array
    {
        return [
            'type' => LocationType::class,
        ];
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Location, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
