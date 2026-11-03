<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\LocationType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\SiteRequest;
use App\Models\Client;
use App\Models\Location;
use App\Models\Site;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class SiteController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Site::class);

        return view('sites.index', [
            'sites' => Site::query()->with(['client', 'supervisor'])->orderBy('name')->paginate(20),
        ]);
    }

    public function create(TenantContext $context): View
    {
        Gate::authorize('create', Site::class);

        $site = new Site(['timezone' => $context->tenant()?->timezone, 'is_active' => true]);

        return view('sites.form', ['site' => $site, ...$this->formOptions()]);
    }

    public function store(SiteRequest $request): RedirectResponse
    {
        $site = Site::create($request->validated());

        return redirect()->route('sites.show', $site)->with('status', "{$site->name} was created.");
    }

    public function show(Site $site): View
    {
        Gate::authorize('view', $site);

        $site->load(['client', 'supervisor', 'coverage']);
        $locations = $site->locations()->orderBy('path')->get();

        return view('sites.show', [
            'site' => $site,
            'tree' => $locations->groupBy(fn (Location $location) => $location->parent_id ?? 0),
            'locationOptions' => $locations->pluck('path', 'id'),
            'locationTypes' => LocationType::cases(),
            'fieldUsers' => $this->fieldUsers(),
        ]);
    }

    public function edit(Site $site): View
    {
        Gate::authorize('update', $site);

        return view('sites.form', ['site' => $site, ...$this->formOptions()]);
    }

    public function update(SiteRequest $request, Site $site): RedirectResponse
    {
        $site->update($request->validated());

        return redirect()->route('sites.show', $site)->with('status', "{$site->name} was updated.");
    }

    public function destroy(Site $site): RedirectResponse
    {
        Gate::authorize('delete', $site);

        $site->delete();

        return redirect()->route('sites.index')->with('status', "{$site->name} was deleted.");
    }

    /**
     * @return array{clients: Collection<int, string>, supervisors: Collection<int, string>, timezones: list<string>}
     */
    private function formOptions(): array
    {
        return [
            'clients' => Client::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'supervisors' => User::query()
                ->where('is_active', true)
                ->role([UserRole::Supervisor->value, UserRole::TenantAdmin->value])
                ->orderBy('name')
                ->pluck('name', 'id'),
            'timezones' => timezone_identifiers_list(),
        ];
    }

    /**
     * @return Collection<int, User>
     */
    private function fieldUsers(): Collection
    {
        return User::query()
            ->with('roles')
            ->where('is_active', true)
            ->role(array_map(fn (UserRole $role) => $role->value, UserRole::fieldRoles()))
            ->orderBy('name')
            ->get();
    }
}
