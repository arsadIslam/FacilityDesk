<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WorkOrderStatus;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\WorkOrderStatusHistoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only audit of every status change; written by ChangeStatus.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $work_order_id
 * @property WorkOrderStatus|null $from_status
 * @property WorkOrderStatus $to_status
 * @property int|null $changed_by
 * @property string|null $note
 * @property Carbon $created_at
 */
class WorkOrderStatusHistory extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<WorkOrderStatusHistoryFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'from_status',
        'to_status',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'from_status' => WorkOrderStatus::class,
            'to_status' => WorkOrderStatus::class,
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
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by')->withTrashed();
    }
}
