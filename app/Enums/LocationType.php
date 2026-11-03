<?php

declare(strict_types=1);

namespace App\Enums;

enum LocationType: string
{
    case Building = 'building';
    case Floor = 'floor';
    case Room = 'room';
    case Area = 'area';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
