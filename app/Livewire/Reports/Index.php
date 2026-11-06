<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Actions\Reports\GenerateMonthlyReports;
use App\Models\Report;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Batch;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Generate a month's client reports and watch the batch finish. The page
 * polls only while a batch is running.
 *
 * @property-read Collection<int, Report> $reports
 */
#[Title('Reports')]
final class Index extends Component
{
    public string $month = '';

    public ?string $flash = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', Report::class);

        $this->month = $this->monthOptions()[1] ?? $this->monthOptions()[0];
    }

    public function generate(GenerateMonthlyReports $generate): void
    {
        Gate::authorize('create', Report::class);
        $this->validate(['month' => ['required', 'in:'.implode(',', $this->monthOptions())]]);

        $batch = $generate->handle($this->tenant(), CarbonImmutable::createFromFormat('!Y-m', $this->month, $this->tenant()->timezone) ?: CarbonImmutable::now(), $this->user());

        $this->flash = $batch === null
            ? 'Every client already has a report for that month.'
            : "Generating {$batch->totalJobs} report(s)…";
        unset($this->reports);
    }

    /**
     * @return Collection<int, Report>
     */
    #[Computed]
    public function reports(): Collection
    {
        return Report::query()->with(['client', 'requester'])->latest('month')->orderBy('client_id')->limit(100)->get();
    }

    /**
     * Progress per running batch, keyed by batch id.
     *
     * @return array<string, int>
     */
    #[Computed]
    public function progress(): array
    {
        return $this->reports
            ->filter(fn (Report $r) => $r->status->isRunning() && $r->batch_id !== null)
            ->pluck('batch_id')
            ->unique()
            ->mapWithKeys(fn (string $id) => [$id => Bus::findBatch($id)?->progress() ?? 0])
            ->all();
    }

    public function isRunning(): bool
    {
        return $this->reports->contains(fn (Report $r) => $r->status->isRunning());
    }

    /**
     * The last 12 local months, newest first ("Y-m").
     *
     * @return list<string>
     */
    public function monthOptions(): array
    {
        $now = CarbonImmutable::now()->setTimezone($this->tenant()->timezone)->startOfMonth();

        return array_map(fn (int $i) => $now->subMonthsNoOverflow($i)->format('Y-m'), range(0, 11));
    }

    public function render(): View
    {
        return view('livewire.reports.index', ['months' => $this->monthOptions()]);
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
