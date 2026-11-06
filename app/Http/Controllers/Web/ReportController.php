<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ReportController extends Controller
{
    /**
     * Signed (expiring) and authorised, like attachments.
     */
    public function download(Report $report, string $format): StreamedResponse
    {
        Gate::authorize('view', $report);

        $path = match ($format) {
            'pdf' => $report->pdf_path,
            'xlsx' => $report->xlsx_path,
            default => null,
        };

        abort_if($report->status !== ReportStatus::Completed || $path === null || $report->disk === null, 404);

        return Storage::disk($report->disk)->download($path, $report->fileName($format));
    }
}
