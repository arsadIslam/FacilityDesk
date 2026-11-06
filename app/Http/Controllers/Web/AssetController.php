<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\AssetStatus;
use App\Enums\WorkOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\AssetRequest;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Location;
use App\Models\Site;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\QrCodeGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class AssetController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Asset::class);

        $siteId = $request->integer('site') ?: null;

        return view('assets.index', [
            'assets' => Asset::query()
                ->with(['site', 'location', 'category'])
                ->when($siteId, fn ($q) => $q->where('site_id', $siteId))
                ->orderBy('code')
                ->paginate(25)
                ->withQueryString(),
            'sites' => Site::query()->orderBy('name')->pluck('name', 'id'),
            'siteId' => $siteId,
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Asset::class);

        return view('assets.form', ['asset' => new Asset(['site_id' => $request->integer('site') ?: null]), ...$this->formOptions()]);
    }

    public function store(AssetRequest $request): RedirectResponse
    {
        $asset = Asset::create($request->validated());

        return redirect()->route('assets.show', $asset)->with('status', "{$asset->code} was created. Print its QR label below.");
    }

    public function show(Request $request, Asset $asset, QrCodeGenerator $qr): View
    {
        Gate::authorize('view', $asset);

        /** @var User $user */
        $user = $request->user();
        $asset->load(['site', 'location', 'category']);

        return view('assets.show', [
            'asset' => $asset,
            'qrSvg' => $qr->svg($asset->scanUrl()),
            'workOrders' => $asset->workOrders()
                ->visibleTo($user)
                ->with(['assignee', 'site'])
                ->latest()
                ->limit(20)
                ->get(),
        ]);
    }

    public function edit(Asset $asset): View
    {
        Gate::authorize('update', $asset);

        return view('assets.form', ['asset' => $asset, ...$this->formOptions()]);
    }

    public function update(AssetRequest $request, Asset $asset): RedirectResponse
    {
        $asset->update($request->validated());

        return redirect()->route('assets.show', $asset)->with('status', "{$asset->code} was updated.");
    }

    public function destroy(Asset $asset): RedirectResponse
    {
        Gate::authorize('delete', $asset);

        // Soft delete; work orders keep their history (asset_id is nulled only on a hard delete).
        $asset->delete();

        return redirect()->route('assets.index')->with('status', "{$asset->code} was deleted.");
    }

    /**
     * A printable SVG label: QR code plus the asset code and name.
     */
    public function label(Asset $asset, QrCodeGenerator $qr): Response
    {
        Gate::authorize('view', $asset);

        $svg = view('assets.label', ['asset' => $asset, 'qrSvg' => $qr->svg($asset->scanUrl(), 300)])->render();

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'attachment; filename="'.Str::slug($asset->code).'-qr.svg"',
        ]);
    }

    /**
     * Scan target of the printed QR code (/a/{qr_token}): the asset, its open
     * work orders, and a shortcut to raise one with everything prefilled.
     */
    public function scan(Request $request, Asset $asset): View
    {
        Gate::authorize('view', $asset);

        /** @var User $user */
        $user = $request->user();
        $asset->load(['site', 'location', 'category']);

        return view('assets.scan', [
            'asset' => $asset,
            'openWorkOrders' => $asset->workOrders()
                ->visibleTo($user)
                ->whereNotIn('status', WorkOrderStatus::finishedValues())
                ->with(['assignee', 'site'])
                ->latest()
                ->get(),
            'canRaise' => $user->can('create', WorkOrder::class),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'sites' => Site::query()->orderBy('name')->pluck('name', 'id'),
            // Grouped by site for <optgroup>; AssetRequest enforces the pairing.
            'locationsBySite' => Location::query()->with('site')->orderBy('path')->get()->groupBy('site_id'),
            'categories' => Category::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'statuses' => AssetStatus::cases(),
        ];
    }
}
