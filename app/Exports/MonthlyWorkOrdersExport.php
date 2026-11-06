<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\WorkOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * One row per work order raised in the month. Times are in the site's timezone.
 *
 * @implements WithMapping<WorkOrder>
 */
final class MonthlyWorkOrdersExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithTitle
{
    /**
     * @param  Collection<int, WorkOrder>  $workOrders
     */
    public function __construct(
        private readonly Collection $workOrders,
        private readonly string $title,
    ) {}

    /**
     * @return Collection<int, WorkOrder>
     */
    public function collection(): Collection
    {
        return $this->workOrders;
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            'Number', 'Raised', 'Site', 'Location', 'Category', 'Title', 'Priority', 'Status',
            'Assignee', 'Responded', 'Resolved', 'Closed', 'Response SLA', 'Resolution SLA', 'Hold (min)',
        ];
    }

    /**
     * @param  WorkOrder  $row
     * @return list<string|int>
     */
    public function map($row): array
    {
        $tz = $row->site->timezone;
        $time = fn (?\DateTimeInterface $t) => $t === null ? '' : Carbon::instance($t)->timezone($tz)->format('Y-m-d H:i');

        return [
            $row->number,
            $time($row->created_at),
            $row->site->name,
            (string) $row->location->path,
            $row->category->name,
            // A leading "=", "+", "-" or "@" would make Excel evaluate the cell (CSV/formula injection).
            preg_replace('/^[=+\-@]/', "'$0", $row->title) ?? $row->title,
            $row->priority->label(),
            $row->status->label(),
            $row->assignee->name ?? '',
            $time($row->responded_at),
            $time($row->resolved_at),
            $time($row->closed_at),
            $row->is_response_breached ? 'Breached' : 'Met',
            $row->is_resolution_breached ? 'Breached' : 'Met',
            $row->hold_minutes,
        ];
    }

    public function title(): string
    {
        return $this->title;
    }
}
