<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\WorkOrderCommentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $work_order_id
 * @property int $user_id
 * @property string $body
 * @property bool $is_internal
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class WorkOrderComment extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<WorkOrderCommentFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'body',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'is_internal' => false,
    ];

    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<WorkOrder, $this>
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * Internal comments are only returned to users allowed to see them.
     *
     * @param  Builder<WorkOrderComment>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->can('viewInternalComments', WorkOrder::class)) {
            $query->where('is_internal', false);
        }
    }
}
