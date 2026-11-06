<?php

declare(strict_types=1);

namespace App\Livewire\WorkOrders;

use App\Enums\Priority;
use App\Enums\UserRole;
use App\Enums\WorkOrderStatus;
use App\Models\Category;
use App\Models\Site;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\TenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The work order list ("board"). Filters live in the query string so a
 * filtered view can be bookmarked or shared.
 */
#[Title('Work orders')]
final class Index extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $priority = '';

    #[Url(as: 'site', except: '')]
    public string $siteId = '';

    #[Url(as: 'category', except: '')]
    public string $categoryId = '';

    #[Url(as: 'assignee', except: '')]
    public string $assigneeId = '';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    #[Url(except: false)]
    public bool $breached = false;

    /** "due" = soonest resolution deadline first (technicians' default), "newest" = latest raised. */
    #[Url(except: '')]
    public string $sort = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', WorkOrder::class);
    }

    public function updated(string $property): void
    {
        if ($property !== 'paginators') {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'status', 'priority', 'siteId', 'categoryId', 'assigneeId', 'from', 'to', 'breached', 'sort']);
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, WorkOrder>
     */
    #[Computed]
    public function workOrders(): LengthAwarePaginator
    {
        $query = WorkOrder::query()
            ->visibleTo($this->user())
            ->with(['site', 'location', 'category', 'assignee']);

        $this->applyFilters($query);

        $sortByDue = $this->sort === 'due' || ($this->sort === '' && $this->user()->hasUserRole(UserRole::Technician));

        $sortByDue
            ? $query->orderBy('resolution_due_at')->orderBy('id')
            : $query->latest('created_at')->latest('id');

        return $query->paginate(20);
    }

    /**
     * @return Collection<int, string>
     */
    #[Computed]
    public function sites(): Collection
    {
        $query = Site::query()->orderBy('name');

        if ($this->user()->hasUserRole(UserRole::ClientUser)) {
            $query->where('client_id', $this->user()->client_id);
        }

        return $query->pluck('name', 'id');
    }

    /**
     * @return Collection<int, string>
     */
    #[Computed]
    public function categories(): Collection
    {
        return Category::query()->orderBy('name')->pluck('name', 'id');
    }

    /**
     * @return Collection<int, string>
     */
    #[Computed]
    public function technicians(): Collection
    {
        return User::query()->role(UserRole::Technician->value)->orderBy('name')->pluck('name', 'id');
    }

    public function render(): View
    {
        return view('livewire.work-orders.index', [
            'statuses' => WorkOrderStatus::cases(),
            'priorities' => Priority::cases(),
            'canFilterByAssignee' => ! $this->user()->hasUserRole(UserRole::Technician, UserRole::ClientUser),
        ]);
    }

    /**
     * @param  Builder<WorkOrder>  $query
     */
    private function applyFilters(Builder $query): void
    {
        $search = trim($this->search);

        $query
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('number', 'like', '%'.$search.'%')
                ->orWhere('title', 'like', '%'.$search.'%')))
            ->when(WorkOrderStatus::tryFrom($this->status), fn (Builder $q, WorkOrderStatus $status) => $q->where('status', $status->value))
            ->when(Priority::tryFrom($this->priority), fn (Builder $q, Priority $priority) => $q->where('priority', $priority->value))
            ->when(ctype_digit($this->siteId), fn (Builder $q) => $q->where('site_id', (int) $this->siteId))
            ->when(ctype_digit($this->categoryId), fn (Builder $q) => $q->where('category_id', (int) $this->categoryId))
            ->when(ctype_digit($this->assigneeId), fn (Builder $q) => $q->where('assigned_to', (int) $this->assigneeId))
            ->when($this->date($this->from), fn (Builder $q, Carbon $from) => $q->where('created_at', '>=', $from->startOfDay()->utc()))
            ->when($this->date($this->to), fn (Builder $q, Carbon $to) => $q->where('created_at', '<=', $to->endOfDay()->utc()))
            ->when($this->breached, fn (Builder $q) => $q->breached());
    }

    /**
     * A Y-m-d filter value as a date in the tenant's timezone, or null if invalid.
     */
    private function date(string $value): ?Carbon
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        $timezone = app(TenantContext::class)->tenant()->timezone ?? 'UTC';
        $date = Carbon::createFromFormat('Y-m-d', $value, $timezone);

        return $date instanceof Carbon ? $date : null;
    }

    private function user(): User
    {
        /** @var User */
        return Auth::user();
    }
}
