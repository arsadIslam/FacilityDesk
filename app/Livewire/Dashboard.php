<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Enums\WorkOrderStatus;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\DashboardStats;
use App\Services\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Supervisors and admins get the tenant-wide, cached numbers with site and
 * date filters; everyone else gets a short personal summary.
 */
#[Title('Dashboard')]
final class Dashboard extends Component
{
    #[Url(as: 'site', except: '')]
    public string $siteId = '';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    public function mount(): void
    {
        $local = CarbonImmutable::now()->setTimezone($this->tenant()->timezone);
        $this->from = $this->validDate($this->from) ? $this->from : $local->startOfMonth()->toDateString();
        $this->to = $this->validDate($this->to) ? $this->to : $local->toDateString();
    }

    public function isManager(): bool
    {
        return $this->user()->hasPermission(Permission::ViewAllWorkOrders);
    }

    /**
     * @return array<string, mixed>|null
     */
    #[Computed]
    public function stats(): ?array
    {
        if (! $this->isManager()) {
            return null;
        }

        $tz = $this->tenant()->timezone;
        $from = $this->validDate($this->from) ? CarbonImmutable::parse($this->from, $tz) : CarbonImmutable::now($tz)->startOfMonth();
        $to = $this->validDate($this->to) ? CarbonImmutable::parse($this->to, $tz) : CarbonImmutable::now($tz);

        if ($to->lessThan($from)) {
            [$from, $to] = [$to, $from];
        }

        return app(DashboardStats::class)->forPeriod($this->tenant(), $from, $to, ctype_digit($this->siteId) ? (int) $this->siteId : null);
    }

    /**
     * @return Collection<int, string>
     */
    #[Computed]
    public function sites(): Collection
    {
        return Site::query()->orderBy('name')->pluck('name', 'id');
    }

    /**
     * @return array{open: int, overdue: int, due_today: int, awaiting_confirmation: int}
     */
    #[Computed]
    public function mine(): array
    {
        $user = $this->user();
        $mine = fn () => WorkOrder::query()->visibleTo($user)->whereNotIn('status', WorkOrderStatus::finishedValues());
        $today = CarbonImmutable::now()->setTimezone($this->tenant()->timezone);

        return [
            'open' => $mine()->count(),
            'overdue' => $mine()->breached()->count(),
            'due_today' => $mine()->whereBetween('resolution_due_at', [$today->startOfDay()->utc(), $today->endOfDay()->utc()])->count(),
            'awaiting_confirmation' => $user->hasUserRole(UserRole::ClientUser)
                ? WorkOrder::query()->visibleTo($user)->where('status', WorkOrderStatus::Resolved->value)->count()
                : 0,
        ];
    }

    public function render(): View
    {
        return view('livewire.dashboard', [
            'tenant' => $this->tenant(),
            'isManager' => $this->isManager(),
            'isClient' => $this->user()->hasUserRole(UserRole::ClientUser),
        ]);
    }

    private function validDate(string $value): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 && strtotime($value) !== false;
    }

    private function tenant(): Tenant
    {
        /** @var Tenant */
        return app(TenantContext::class)->tenant();
    }

    private function user(): User
    {
        /** @var User */
        return Auth::user();
    }
}
