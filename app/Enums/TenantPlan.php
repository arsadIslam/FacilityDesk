<?php

declare(strict_types=1);

namespace App\Enums;

enum TenantPlan: string
{
    case Trial = 'trial';
    case Basic = 'basic';
    case Pro = 'pro';
}
