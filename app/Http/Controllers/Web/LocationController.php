<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Locations\DeleteLocation;
use App\Actions\Locations\SaveLocation;
use App\Enums\LocationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\LocationRequest;
use App\Models\Location;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Nested under sites with scoped bindings: a location id that does not
 * belong to the site in the URL is a 404.
 */
final class LocationController extends Controller
{
    public function store(LocationRequest $request, Site $site, SaveLocation $saveLocation): RedirectResponse
    {
        $location = $saveLocation->handle($site, $request->type(), $request->string('name')->toString(), $request->parentId());

        return redirect()->route('sites.show', $site)->with('status', "{$location->path} was added.");
    }

    public function edit(Site $site, Location $location): View
    {
        Gate::authorize('update', $site);

        return view('locations.edit', [
            'site' => $site,
            'location' => $location,
            'locationTypes' => LocationType::cases(),
            'parentOptions' => $site->locations()->whereKeyNot($location->id)->orderBy('path')->pluck('path', 'id'),
        ]);
    }

    public function update(LocationRequest $request, Site $site, Location $location, SaveLocation $saveLocation): RedirectResponse
    {
        $saveLocation->handle($site, $request->type(), $request->string('name')->toString(), $request->parentId(), $location);

        return redirect()->route('sites.show', $site)->with('status', "{$location->path} was updated.");
    }

    public function destroy(Site $site, Location $location, DeleteLocation $deleteLocation): RedirectResponse
    {
        Gate::authorize('update', $site);

        $deleteLocation->handle($location);

        return redirect()->route('sites.show', $site)->with('status', "{$location->path} was deleted.");
    }
}
