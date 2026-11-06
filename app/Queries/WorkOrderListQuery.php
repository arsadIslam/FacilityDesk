<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\Priority;
use App\Enums\UserRole;
use App\Enums\WorkOrderStatus;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * The work order list query, shared by the Livewire list, the API and the
 * benchmark command, so all three exercise exactly the same SQL.
 *
 * Filters are already-untrusted strings from the UI or the API; anything
 * malformed is ignored rather than passed to the database.
 */
final class WorkOrderListQuery
{
    /** @var list<string> */
    public const RELATIONS = ['site', 'location', 'category', 'asset', 'assignee', 'raisedBy'];

    public function __construct(private readonly TenantContext $context) {}

    /**
     * @param  array{status?: string|null, priority?: string|null, site_id?: int|string|null, category_id?: int|string|null, assigned_to?: int|string|null, from?: string|null, to?: string|null, breached?: bool|null, search?: string|null, sort?: string|null}  $filters
     * @return Builder<WorkOrder>
     */
    public function build(User $user, array $filters = []): Builder
    {
        $query = WorkOrder::query()->visibleTo($user)->with(self::RELATIONS);
        $search = trim((string) ($filters['search'] ?? ''));

        $query
            ->when($search !== '', fn (Builder $q) => $this->search($q, $search))
            ->when(WorkOrderStatus::tryFrom((string) ($filters['status'] ?? '')), fn (Builder $q, WorkOrderStatus $s) => $q->where('status', $s->value))
            ->when(Priority::tryFrom((string) ($filters['priority'] ?? '')), fn (Builder $q, Priority $p) => $q->where('priority', $p->value))
            ->when($this->id($filters['site_id'] ?? null), fn (Builder $q, int $id) => $q->where('site_id', $id))
            ->when($this->id($filters['category_id'] ?? null), fn (Builder $q, int $id) => $q->where('category_id', $id))
            ->when($this->id($filters['assigned_to'] ?? null), fn (Builder $q, int $id) => $q->where('assigned_to', $id))
            ->when($this->date($filters['from'] ?? null), fn (Builder $q, Carbon $d) => $q->where('created_at', '>=', $d->startOfDay()->utc()))
            ->when($this->date($filters['to'] ?? null), fn (Builder $q, Carbon $d) => $q->where('created_at', '<=', $d->endOfDay()->utc()))
            ->when((bool) ($filters['breached'] ?? false), fn (Builder $q) => $q->breached());

        $sort = $filters['sort'] ?? '';
        $byDue = $sort === 'due' || ($sort === '' && $user->hasUserRole(UserRole::Technician));

        return $byDue
            ? $query->orderBy('resolution_due_at')->orderBy('id')
            : $query->orderByDesc('created_at')->orderByDesc('id');
    }

    /**
     * - A full number ("WO-2026-00123") is an exact lookup on the unique index.
     * - Digits ("00123") match the number only.
     * - Words match the title: on MySQL through the FULLTEXT index with word
     *   prefixes ("leak" finds "leakage"), elsewhere (SQLite) with LIKE.
     *
     * @param  Builder<WorkOrder>  $query
     */
    private function search(Builder $query, string $search): void
    {
        if (preg_match('/^WO-\d{4}-\d{5,6}$/i', $search) === 1) {
            $query->where('number', strtoupper($search));

            return;
        }

        if (ctype_digit($search)) {
            $query->where('number', 'like', '%'.$search.'%');

            return;
        }

        if ($query->getModel()->getConnection()->getDriverName() === 'mysql') {
            // Boolean mode: every word required, each as a prefix. Operators typed
            // by the user are stripped so they cannot change the query's meaning.
            $words = preg_split('/\s+/', trim((string) preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $search))) ?: [];
            $terms = implode(' ', array_map(fn (string $w) => '+'.$w.'*', array_filter($words, fn (string $w) => mb_strlen($w) >= 3)));

            if ($terms !== '') {
                $query->whereFullText('title', $terms, ['mode' => 'boolean']);

                return;
            }
        }

        $query->where('title', 'like', '%'.addcslashes($search, '%_\\').'%');
    }

    private function id(int|string|null $value): ?int
    {
        return is_int($value) || (is_string($value) && ctype_digit($value)) ? (int) $value : null;
    }

    private function date(?string $value): ?Carbon
    {
        if ($value === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        $date = Carbon::createFromFormat('Y-m-d', $value, $this->context->tenant()->timezone ?? 'UTC');

        return $date instanceof Carbon ? $date : null;
    }
}
