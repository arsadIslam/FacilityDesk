<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TenantPlan;
use App\Enums\TenantStatus;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $email
 * @property string|null $phone
 * @property TenantPlan $plan
 * @property TenantStatus $status
 * @property string $timezone
 * @property string|null $logo_path
 * @property array<string, mixed>|null $settings
 * @property Carbon|null $trial_ends_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    /**
     * plan, status and trial_ends_at are deliberately not fillable: they are
     * platform-controlled and must never come from a signup or settings form.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'email',
        'phone',
        'timezone',
        'logo_path',
        'settings',
    ];

    /**
     * Mirrors the column defaults so a freshly created model is complete in memory.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'plan' => 'trial',
        'status' => 'active',
        'timezone' => 'Asia/Kolkata',
    ];

    protected function casts(): array
    {
        return [
            'plan' => TenantPlan::class,
            'status' => TenantStatus::class,
            'settings' => 'array',
            'trial_ends_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<Client, $this>
     */
    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    /**
     * @return HasMany<Site, $this>
     */
    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    /**
     * @return HasMany<SlaPolicy, $this>
     */
    public function slaPolicies(): HasMany
    {
        return $this->hasMany(SlaPolicy::class);
    }

    public function isActive(): bool
    {
        return $this->status === TenantStatus::Active;
    }

    /**
     * Absolute URL on this tenant's subdomain, keeping APP_URL's scheme and port.
     */
    public function url(string $path = '/'): string
    {
        $appUrl = (string) config('app.url');
        $scheme = parse_url($appUrl, PHP_URL_SCHEME) ?: 'http';
        $port = parse_url($appUrl, PHP_URL_PORT);
        $path = trim($path, '/');

        return sprintf(
            '%s://%s.%s%s%s',
            $scheme,
            $this->slug,
            config('tenancy.domain'),
            $port ? ':'.$port : '',
            $path === '' ? '' : '/'.$path,
        );
    }
}
