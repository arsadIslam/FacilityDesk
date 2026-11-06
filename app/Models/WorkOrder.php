<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Permission;
use App\Enums\Priority;
use App\Enums\UserRole;
use App\Enums\WorkOrderSource;
use App\Enums\WorkOrderStatus;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\WorkOrderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $number
 * @property int $site_id
 * @property int $location_id
 * @property int $category_id
 * @property int|null $asset_id
 * @property int $raised_by
 * @property int|null $assigned_to
 * @property int|null $assigned_by
 * @property string $title
 * @property string|null $description
 * @property Priority $priority
 * @property WorkOrderStatus $status
 * @property WorkOrderSource $source
 * @property Carbon $response_due_at
 * @property Carbon $resolution_due_at
 * @property Carbon|null $responded_at
 * @property Carbon|null $resolved_at
 * @property Carbon|null $closed_at
 * @property int $hold_minutes
 * @property bool $is_response_breached
 * @property bool $is_resolution_breached
 * @property Carbon|null $breach_notified_at
 * @property string|null $resolution_note
 * @property int|null $client_rating
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
class WorkOrder extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<WorkOrderFactory> */
    use HasFactory;

    use LogsActivity;
    use SoftDeletes;

    /**
     * Only what a requester types. number, status, assignee, SLA dates and
     * every lifecycle timestamp are set by the WorkOrders actions.
     *
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'description',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'open',
        'source' => 'web',
        'hold_minutes' => 0,
        'is_response_breached' => false,
        'is_resolution_breached' => false,
    ];

    protected function casts(): array
    {
        return [
            'priority' => Priority::class,
            'status' => WorkOrderStatus::class,
            'source' => WorkOrderSource::class,
            'response_due_at' => 'datetime',
            'resolution_due_at' => 'datetime',
            'responded_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'breach_notified_at' => 'datetime',
            'hold_minutes' => 'integer',
            'is_response_breached' => 'boolean',
            'is_resolution_breached' => 'boolean',
            'client_rating' => 'integer',
        ];
    }

    /**
     * Audit trail of what people change. SLA flags, due dates and other
     * system-maintained columns are left out; the status timeline has the detail.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('work_orders')
            ->logOnly(['title', 'description', 'priority', 'status', 'assigned_to', 'site_id', 'location_id', 'category_id', 'asset_id', 'resolution_note'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
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
     * @return BelongsTo<Asset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function raisedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'raised_by')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by')->withTrashed();
    }

    /**
     * @return HasMany<WorkOrderStatusHistory, $this>
     */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(WorkOrderStatusHistory::class);
    }

    /**
     * @return HasMany<WorkOrderComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(WorkOrderComment::class);
    }

    /**
     * Path of the detail page, relative to the tenant's subdomain.
     */
    public function path(): string
    {
        return '/work-orders/'.$this->id;
    }

    /**
     * Absolute link for emails, which are sent from queue workers where no
     * request (and so no current subdomain) exists.
     */
    public function url(): string
    {
        $tenant = $this->loadMissing('tenant')->getRelation('tenant');

        return $tenant instanceof Tenant ? $tenant->url($this->path()) : url($this->path());
    }

    /**
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /**
     * Appends to the status timeline. History is written in the same
     * transaction as the change, so the two can never disagree.
     */
    public function recordStatus(?WorkOrderStatus $from, WorkOrderStatus $to, ?User $changedBy, ?string $note = null): WorkOrderStatusHistory
    {
        $history = new WorkOrderStatusHistory(['from_status' => $from, 'to_status' => $to, 'note' => $note]);
        $history->tenant_id = $this->tenant_id;
        $history->changed_by = $changedBy?->id;

        $this->statusHistories()->save($history);

        return $history;
    }

    /**
     * Work orders $user may see. Mirrors WorkOrderPolicy::view() for lists.
     *
     * @param  Builder<WorkOrder>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->hasPermission(Permission::ViewAllWorkOrders)) {
            return;
        }

        $query->where(function (Builder $query) use ($user): void {
            $query->where('raised_by', $user->id)->orWhere('assigned_to', $user->id);

            if ($user->hasUserRole(UserRole::ClientUser) && $user->client_id !== null) {
                $query->orWhereHas('site', fn (Builder $site) => $site->where('client_id', $user->client_id));
            }
        });
    }

    /**
     * Breached: flagged by sla:check-breaches or ChangeStatus, or past a due
     * date right now (so the list is accurate between checker runs). On-hold
     * work orders only count once flagged; their clock is paused.
     *
     * @param  Builder<WorkOrder>  $query
     */
    public function scopeBreached(Builder $query): void
    {
        $now = now();

        $query->where(function (Builder $query) use ($now): void {
            $query->where('is_response_breached', true)
                ->orWhere('is_resolution_breached', true)
                ->orWhere(fn (Builder $q) => $q
                    ->whereNull('responded_at')
                    ->where('status', '!=', WorkOrderStatus::Cancelled->value)
                    ->where('response_due_at', '<', $now))
                ->orWhere(fn (Builder $q) => $q
                    ->whereNotIn('status', [...WorkOrderStatus::finishedValues(), WorkOrderStatus::OnHold->value])
                    ->where('resolution_due_at', '<', $now));
        });
    }
}
