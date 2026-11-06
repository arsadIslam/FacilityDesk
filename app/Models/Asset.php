<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AssetStatus;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\AssetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $site_id
 * @property int $location_id
 * @property int $category_id
 * @property string $name
 * @property string $code
 * @property string $qr_token
 * @property string|null $make
 * @property string|null $model
 * @property string|null $serial_no
 * @property Carbon|null $installed_at
 * @property Carbon|null $warranty_until
 * @property AssetStatus $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
class Asset extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<AssetFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * site_id, location_id and category_id are validated against the tenant
     * (and the location against the site) by AssetRequest. qr_token is
     * generated, never accepted from input.
     *
     * @var list<string>
     */
    protected $fillable = [
        'site_id',
        'location_id',
        'category_id',
        'name',
        'code',
        'make',
        'model',
        'serial_no',
        'installed_at',
        'warranty_until',
        'status',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'working',
    ];

    protected static function booted(): void
    {
        static::creating(function (Asset $asset): void {
            $asset->qr_token ??= (string) Str::ulid();
        });
    }

    protected function casts(): array
    {
        return [
            'status' => AssetStatus::class,
            'installed_at' => 'date',
            'warranty_until' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<WorkOrder, $this>
     */
    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    public function isUnderWarranty(): bool
    {
        return $this->warranty_until !== null && $this->warranty_until->endOfDay()->isFuture();
    }

    /**
     * What the QR code encodes: the scan page on this tenant's subdomain.
     */
    public function scanUrl(): string
    {
        $tenant = $this->loadMissing('tenant')->getRelation('tenant');

        return $tenant instanceof Tenant ? $tenant->url('a/'.$this->qr_token) : url('a/'.$this->qr_token);
    }
}
