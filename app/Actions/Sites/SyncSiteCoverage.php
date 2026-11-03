<?php

declare(strict_types=1);

namespace App\Actions\Sites;

use App\Models\Site;

final class SyncSiteCoverage
{
    /**
     * @param  list<int>  $userIds  already validated as field users of this tenant
     */
    public function handle(Site $site, array $userIds): void
    {
        $site->coverage()->syncWithPivotValues($userIds, ['tenant_id' => $site->tenant_id]);
    }
}
