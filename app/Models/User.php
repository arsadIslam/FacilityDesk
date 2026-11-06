<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property int|null $tenant_id
 * @property int|null $client_id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string $password
 * @property bool $is_active
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $last_login_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
class User extends Authenticatable implements MustVerifyEmail
{
    /**
     * The tenant scope also applies to the auth provider, so a user can only
     * log in on their own tenant's subdomain.
     */
    use BelongsToTenant;

    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;
    use HasRoles;
    use Notifiable;
    use SoftDeletes;

    /**
     * tenant_id and client_id are never mass-assignable; client_id is set by
     * the user actions after validating it against the tenant.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'is_active',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Categories a technician is skilled in.
     *
     * @return BelongsToMany<Category, $this>
     */
    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'technician_skills');
    }

    /**
     * Sites this user covers.
     *
     * @return BelongsToMany<Site, $this>
     */
    public function sites(): BelongsToMany
    {
        return $this->belongsToMany(Site::class);
    }

    /**
     * @return HasMany<WorkOrder, $this>
     */
    public function assignedWorkOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class, 'assigned_to');
    }

    public function isSuperAdmin(): bool
    {
        return $this->tenant_id === null;
    }

    /**
     * The user's role in the current tenant. Users hold exactly one tenant role.
     */
    public function role(): ?UserRole
    {
        $name = $this->getRoleNames()->first();

        return is_string($name) ? UserRole::tryFrom($name) : null;
    }

    public function hasUserRole(UserRole ...$roles): bool
    {
        return in_array($this->role(), $roles, true);
    }

    /**
     * checkPermissionTo() returns false (instead of throwing) for a permission
     * that has not been seeded yet, so a missing seed fails closed.
     */
    public function hasPermission(Permission $permission): bool
    {
        return $this->checkPermissionTo($permission->value);
    }

    /**
     * Invited users are unverified until they accept and set a password.
     */
    public function hasPendingInvitation(): bool
    {
        return $this->email_verified_at === null;
    }
}
