<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\LocationResource;
use App\Http\Resources\SiteResource;
use App\Models\Location;
use App\Models\Site;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;

#[Group('Sites')]
#[Authenticated]
final class SiteController extends Controller
{
    /**
     * List sites
     *
     * Active sites the caller can raise or work on work orders for: all for
     * staff, their client's for client users, covered sites for technicians.
     */
    #[ResponseFromApiResource(SiteResource::class, Site::class, collection: true, with: ['client'])]
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        return SiteResource::collection(
            Site::query()->availableTo($user)->where('is_active', true)->with('client')->orderBy('name')->get(),
        );
    }

    /**
     * List a site's locations
     *
     * Flat list ordered by path; rebuild the tree from parent_id.
     */
    #[ResponseFromApiResource(LocationResource::class, Location::class, collection: true)]
    public function locations(Request $request, Site $site): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless(Site::query()->availableTo($user)->whereKey($site->id)->exists(), 403);

        return LocationResource::collection($site->locations()->orderBy('path')->get());
    }
}
