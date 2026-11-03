<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\Priority;
use App\Http\Controllers\Controller;
use App\Http\Requests\SlaPolicyRequest;
use App\Models\SlaPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * One row per priority is seeded at signup; tenants only edit the targets.
 */
final class SlaPolicyController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', SlaPolicy::class);

        $order = array_map(fn (Priority $priority) => $priority->value, Priority::cases());

        return view('sla-policies.index', [
            'policies' => SlaPolicy::query()->get()
                ->sortBy(fn (SlaPolicy $policy) => array_search($policy->priority->value, $order, true))
                ->values(),
        ]);
    }

    public function update(SlaPolicyRequest $request, SlaPolicy $slaPolicy): RedirectResponse
    {
        $slaPolicy->update($request->validated());

        return redirect()->route('sla-policies.index')->with('status', "{$slaPolicy->priority->label()} SLA was updated.");
    }
}
