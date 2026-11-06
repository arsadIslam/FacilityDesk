<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\SiteFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $client_id
 * @property string $name
 * @property string $code
 * @property string|null $address
 * @property string|null $city
 * @property string $timezone
 * @property int|null $supervisor_id
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
class Site extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<SiteFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * client_id and supervisor_id are fillable because SiteRequest validates
     * both against the current tenant before they reach the model.
     *
     * @var list<string>
     */
    protected $fillable = [
        'client_id',
        'name',
        'code',
        'address',
        'city',
        'timezone',
        'supervisor_id',
        'is_active',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Sites a user may raise or work on work orders for: all of them for
     * staff who can view sites, their client's for Client Users, and the
     * sites they cover for everyone else (technicians).
     *
     * @param  Builder<Site>  $query
     */
    public function scopeAvailableTo(Builder $query, User $user): void
    {
        if ($user->hasPermission(Permission::ViewSites)) {
            return;
        }

        if ($user->hasUserRole(UserRole::ClientUser)) {
            $query->where('client_id', $user->client_id ?? 0);

            return;
        }

        $query->whereHas('coverage', fn (Builder $q) => $q->whereKey($user->id));
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    /**
     * @return HasMany<Location, $this>
     */
    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    /**
     * @return HasMany<WorkOrder, $this>
     */
    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    /**
     * Supervisors and technicians who cover this site.
     *
     * @return BelongsToMany<User, $this>
     */
    public function coverage(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
