<?php

declare(strict_types=1);

namespace App\Enums;

enum Priority: string
{
    case Critical = 'critical';
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Default SLA targets seeded for every new tenant, in minutes.
     *
     * @return array{response: int, resolution: int}
     */
    public function defaultSla(): array
    {
        return match ($this) {
            self::Critical => ['response' => 15, 'resolution' => 240],
            self::High => ['response' => 60, 'resolution' => 480],
            self::Medium => ['response' => 240, 'resolution' => 1440],
            self::Low => ['response' => 1440, 'resolution' => 4320],
        };
    }
}
