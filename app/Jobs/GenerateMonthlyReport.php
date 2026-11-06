<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ReportStatus;
use App\Exports\MonthlyWorkOrdersExport;
use App\Jobs\Middleware\SetTenantContext;
use App\Models\Report;
use App\Services\MonthlyReportBuilder;
use App\Services\TenantContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

/**
 * One client's monthly PDF and Excel, as part of a Bus::batch() so the
 * Reports page can show the whole month's progress.
 */
final class GenerateMonthlyReport implements ShouldQueue
{
    use Batchable;
    use Queueable;

    public int $tries = 2;

    /** @var list<int> */
    public array $backoff = [30];

    public int $timeout = 300;

    public function __construct(
        public readonly int $reportId,
        public readonly int $tenantId,
    ) {
        $this->onQueue('reports');
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new SetTenantContext($this->tenantId)];
    }

    public function handle(TenantContext $context, MonthlyReportBuilder $builder): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $report = Report::query()->with('client')->find($this->reportId);
        $tenant = $context->tenant();

        if ($report === null || $tenant === null) {
            return;
        }

        $report->forceFill(['status' => ReportStatus::Processing])->save();

        $data = $builder->build($tenant, $report->client, CarbonImmutable::instance($report->month));
        $disk = (string) config('filesystems.attachments_disk');
        $base = sprintf('tenants/%d/reports/%s/%s', $tenant->id, $report->month->format('Y-m'), Str::slug($report->client->name).'-'.$report->id);

        Storage::disk($disk)->put("{$base}.pdf", Pdf::loadView('reports.monthly', $data)->setPaper('a4')->setOption('isFontSubsettingEnabled', true)->output());
        Storage::disk($disk)->put("{$base}.xlsx", Excel::raw(new MonthlyWorkOrdersExport($data['rows'], $report->month->format('M Y')), ExcelFormat::XLSX));

        $report->forceFill([
            'status' => ReportStatus::Completed,
            'disk' => $disk,
            'pdf_path' => "{$base}.pdf",
            'xlsx_path' => "{$base}.xlsx",
            'error' => null,
            'completed_at' => now(),
        ])->save();
    }

    public function failed(Throwable $exception): void
    {
        // Runs without the tenant middleware, so query explicitly by id.
        Report::query()->withoutGlobalScope('tenant')->whereKey($this->reportId)->update([
            'status' => ReportStatus::Failed->value,
            'error' => Str::limit($exception->getMessage(), 490),
        ]);
    }
}
