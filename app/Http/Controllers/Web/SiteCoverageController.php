<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Sites\SyncSiteCoverage;
use App\Http\Controllers\Controller;
use App\Http\Requests\SiteCoverageRequest;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;

final class SiteCoverageController extends Controller
{
    public function update(SiteCoverageRequest $request, Site $site, SyncSiteCoverage $syncSiteCoverage): RedirectResponse
    {
        $syncSiteCoverage->handle($site, $request->userIds());

        return redirect()->route('sites.show', $site)->with('status', 'Site coverage was updated.');
    }
}
