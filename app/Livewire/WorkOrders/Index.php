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
use App\Queries\WorkOrderListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
        return app(WorkOrderListQuery::class)->build($this->user(), [
            'search' => $this->search,
            'status' => $this->status,
            'priority' => $this->priority,
            'site_id' => $this->siteId,
            'category_id' => $this->categoryId,
            'assigned_to' => $this->assigneeId,
            'from' => $this->from,
            'to' => $this->to,
            'breached' => $this->breached,
            'sort' => $this->sort,
        ])->paginate(20);
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

    private function user(): User
    {
        /** @var User */
        return Auth::user();
    }
}
