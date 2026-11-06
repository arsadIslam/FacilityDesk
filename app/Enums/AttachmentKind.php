<?php

declare(strict_types=1);

namespace App\Enums;

enum AttachmentKind: string
{
    case Before = 'before';
    case After = 'after';
    case General = 'general';

    public function label(): string
    {
        return match ($this) {
            self::Before => 'Before',
            self::After => 'After',
            self::General => 'Photo',
        };
    }
}
