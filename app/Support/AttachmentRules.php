<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Upload rules shared by the Livewire forms and (Phase 6) the API.
 */
final class AttachmentRules
{
    public const MAX_KILOBYTES = 5120;

    public const MAX_PER_WORK_ORDER_CREATE = 5;

    /**
     * "mimes" checks the type guessed from the file's content, and
     * "mimetypes" pins the MIME type, so a renamed .php or .html is rejected.
     *
     * @return list<string>
     */
    public static function file(): array
    {
        return [
            'file',
            'mimes:jpg,jpeg,png,webp,pdf',
            'mimetypes:image/jpeg,image/png,image/webp,application/pdf',
            'max:'.self::MAX_KILOBYTES,
        ];
    }
}
