<?php

declare(strict_types=1);

namespace App\Enums;

enum WorkOrderSource: string
{
    case Web = 'web';
    case Api = 'api';
    case Qr = 'qr';
}
