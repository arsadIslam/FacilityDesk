<?php

declare(strict_types=1);

namespace App\Enums;

enum ReportStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function isRunning(): bool
    {
        return $this === self::Pending || $this === self::Processing;
    }
}
