<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\WorkOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\AssetResource;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Attributes\UrlParam;

#[Group('Assets')]
#[Authenticated]
final class AssetController extends Controller
{
    /**
     * Look up a scanned QR code
     *
     * The token is the last path segment of the URL in the asset's QR code.
     * Returns the asset with the open work orders the caller may see; another
     * workspace's token is a 404.
     */
    #[UrlParam('asset', description: 'The qr_token: last path segment of the URL in the QR code.', example: '01JAXKZ5Q8V2M9T3R7N4HC6WYD')]
    #[ResponseFromApiResource(AssetResource::class, Asset::class, with: ['site', 'location', 'category'])]
    public function qr(Request $request, Asset $asset): AssetResource
    {
        Gate::authorize('view', $asset);

        /** @var User $user */
        $user = $request->user();

        return new AssetResource($asset->load([
            'site', 'location', 'category',
            'workOrders' => fn ($q) => $q->visibleTo($user)
                ->whereNotIn('status', WorkOrderStatus::finishedValues())
                ->with(['assignee'])
                ->latest(),
        ]));
    }
}
