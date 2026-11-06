<?php

declare(strict_types=1);

namespace App\Livewire\Audit;

use App\Enums\Permission;
use App\Models\Activity;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Tenant Admins: who changed what on work orders, users and roles.
 * Entries are tenant-scoped (App\Models\Activity uses BelongsToTenant).
 */
#[Title('Audit log')]
final class Index extends Component
{
    use WithPagination;

    public const LOGS = ['work_orders' => 'Work orders', 'users' => 'Users', 'roles' => 'Roles'];

    #[Url(except: '')]
    public string $log = '';

    #[Url(except: '')]
    public string $event = '';

    #[Url(as: 'by', except: '')]
    public string $causerId = '';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    #[Url(except: '')]
    public string $search = '';

    public function mount(): void
    {
        abort_unless($this->user()->hasPermission(Permission::ViewAuditLog), 403);
    }

    public function updated(string $property): void
    {
        if ($property !== 'paginators') {
            $this->resetPage();
        }
    }

    /**
     * @return LengthAwarePaginator<int, Activity>
     */
    #[Computed]
    public function entries(): LengthAwarePaginator
    {
        $timezone = app(TenantContext::class)->tenant()->timezone ?? 'UTC';
        $date = fn (string $v) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? Carbon::parse($v, $timezone) : null;

        return Activity::query()
            ->with(['causer', 'subject'])
            ->when(array_key_exists($this->log, self::LOGS), fn ($q) => $q->where('log_name', $this->log))
            ->when(in_array($this->event, ['created', 'updated', 'deleted', 'role_changed'], true), fn ($q) => $q->where('event', $this->event))
            ->when(ctype_digit($this->causerId), fn ($q) => $q->where('causer_type', 'user')->where('causer_id', (int) $this->causerId))
            ->when($date($this->from), fn ($q, Carbon $d) => $q->where('created_at', '>=', $d->startOfDay()->utc()))
            ->when($date($this->to), fn ($q, Carbon $d) => $q->where('created_at', '<=', $d->endOfDay()->utc()))
            ->when(trim($this->search) !== '', fn ($q) => $q->where('description', 'like', '%'.trim($this->search).'%'))
            ->latest('id')
            ->paginate(25);
    }

    /**
     * @return Collection<int, string>
     */
    #[Computed]
    public function people(): Collection
    {
        return User::query()->withTrashed()->orderBy('name')->pluck('name', 'id');
    }

    public function render(): View
    {
        return view('livewire.audit.index', ['logs' => self::LOGS]);
    }

    private function user(): User
    {
        /** @var User */
        return Auth::user();
    }
}
