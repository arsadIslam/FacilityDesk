<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AttachmentKind;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\AttachmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $attachable_type
 * @property int $attachable_id
 * @property int $uploaded_by
 * @property AttachmentKind $kind
 * @property string $disk
 * @property string $path
 * @property string|null $thumbnail_path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size_bytes
 * @property Carbon|null $processed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Attachment extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<AttachmentFactory> */
    use HasFactory;

    /**
     * Nothing is mass assignable: StoreAttachment sets every column itself.
     *
     * @var list<string>
     */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'kind' => AttachmentKind::class,
            'size_bytes' => 'integer',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by')->withTrashed();
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * Short-lived signed link to the authorised download route. The route
     * still checks the policy, so a leaked link is useless to other users.
     */
    public function url(bool $thumbnail = false): string
    {
        return URL::temporarySignedRoute(
            'attachments.show',
            now()->addMinutes(30),
            array_filter(['attachment' => $this->id, 'thumb' => $thumbnail && $this->thumbnail_path ? 1 : null]),
        );
    }
}
