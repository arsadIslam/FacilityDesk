<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Priority;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property Priority $default_priority
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Category extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    public const DEFAULTS = ['Electrical', 'Plumbing', 'HVAC', 'Housekeeping', 'Civil', 'Security'];

    /** @var list<string> */
    protected $fillable = [
        'name',
        'default_priority',
        'is_active',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'default_priority' => 'medium',
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'default_priority' => Priority::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * Technicians skilled in this category.
     *
     * @return BelongsToMany<User, $this>
     */
    public function technicians(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'technician_skills');
    }
}
