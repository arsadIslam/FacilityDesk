<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReportStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * One client's monthly report (PDF + Excel), produced by a GenerateMonthlyReport job.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $client_id
 * @property Carbon $month
 * @property ReportStatus $status
 * @property string|null $batch_id
 * @property int|null $requested_by
 * @property string|null $disk
 * @property string|null $pdf_path
 * @property string|null $xlsx_path
 * @property string|null $error
 * @property Carbon|null $completed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Report extends Model
{
    use BelongsToTenant;

    /**
     * Set by GenerateMonthlyReports and the job, never from input.
     *
     * @var list<string>
     */
    protected $fillable = [];

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'month' => 'date',
            'status' => ReportStatus::class,
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by')->withTrashed();
    }

    /**
     * Expiring signed link to the authorised download route.
     */
    public function downloadUrl(string $format): string
    {
        return URL::temporarySignedRoute('reports.download', now()->addMinutes(30), ['report' => $this->id, 'format' => $format]);
    }

    public function fileName(string $extension): string
    {
        return sprintf('%s-%s.%s', Str::slug($this->client->name), $this->month->format('Y-m'), $extension);
    }
}
