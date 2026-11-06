<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\WorkOrder;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class SlaBreached implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  list<'response'|'resolution'>  $breaches  the targets newly missed in this check
     */
    public function __construct(
        public readonly WorkOrder $workOrder,
        public readonly array $breaches,
    ) {}
}
