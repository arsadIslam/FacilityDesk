<?php

declare(strict_types=1);

namespace App\Enums;

enum AssetStatus: string
{
    case Working = 'working';
    case UnderRepair = 'under_repair';
    case Retired = 'retired';

    public function label(): string
    {
        return match ($this) {
            self::Working => 'Working',
            self::UnderRepair => 'Under repair',
            self::Retired => 'Retired',
        };
    }
}
