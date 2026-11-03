<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Tenants\RegisterTenant;
use App\Http\Controllers\Controller;
use App\Http\Requests\TenantSignupRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class TenantSignupController extends Controller
{
    public function create(): View
    {
        return view('signup.create', [
            'domain' => config('tenancy.domain'),
        ]);
    }

    public function store(TenantSignupRequest $request, RegisterTenant $registerTenant): RedirectResponse
    {
        $tenant = $registerTenant->handle(
            companyName: $request->string('company_name')->toString(),
            slug: $request->string('slug')->toString(),
            adminName: $request->string('admin_name')->toString(),
            adminEmail: $request->string('admin_email')->toString(),
            password: $request->string('password')->toString(),
        );

        return redirect()
            ->route('signup.success')
            ->with('workspace', ['name' => $tenant->name, 'url' => $tenant->url()]);
    }

    public function success(): View|RedirectResponse
    {
        $workspace = session('workspace');

        if (! is_array($workspace)) {
            return redirect()->route('signup.create');
        }

        return view('signup.success', ['workspace' => $workspace]);
    }
}
