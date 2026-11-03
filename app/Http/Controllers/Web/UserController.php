<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Users\InviteUser;
use App\Actions\Users\SendInvitation;
use App\Actions\Users\SetUserActive;
use App\Actions\Users\UpdateUser;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\InviteUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Category;
use App\Models\Client;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class UserController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', User::class);

        return view('users.index', [
            'users' => User::query()->with(['roles', 'client'])->orderBy('name')->paginate(20),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', User::class);

        return view('users.create', [
            'roles' => UserRole::tenantRoles(),
            'clients' => Client::query()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function store(InviteUserRequest $request, InviteUser $inviteUser): RedirectResponse
    {
        $user = $inviteUser->handle(
            $request->safe()->only(['name', 'email', 'phone']),
            $request->role(),
            $request->clientId(),
        );

        return redirect()->route('users.index')->with('status', "Invitation sent to {$user->email}.");
    }

    public function edit(User $user): View
    {
        Gate::authorize('update', $user);

        $user->load(['roles', 'skills']);

        return view('users.edit', [
            'user' => $user,
            'roles' => UserRole::tenantRoles(),
            'clients' => Client::query()->orderBy('name')->pluck('name', 'id'),
            'categories' => Category::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user, UpdateUser $updateUser): RedirectResponse
    {
        $updateUser->handle(
            $user,
            $request->safe()->only(['name', 'phone']),
            $request->role(),
            $request->clientId(),
            $request->skillIds(),
        );

        return redirect()->route('users.index')->with('status', "{$user->name} was updated.");
    }

    public function resendInvitation(User $user, SendInvitation $sendInvitation): RedirectResponse
    {
        Gate::authorize('update', $user);

        if (! $user->hasPendingInvitation()) {
            return back()->withErrors(['user' => "{$user->name} has already accepted their invitation."]);
        }

        $sendInvitation->handle($user);

        return back()->with('status', "Invitation re-sent to {$user->email}.");
    }

    public function activate(Request $request, User $user, SetUserActive $setUserActive): RedirectResponse
    {
        Gate::authorize('update', $user);

        /** @var User $actor */
        $actor = $request->user();
        $setUserActive->handle($actor, $user, true);

        return back()->with('status', "{$user->name} was reactivated.");
    }

    public function deactivate(Request $request, User $user, SetUserActive $setUserActive): RedirectResponse
    {
        Gate::authorize('deactivate', $user);

        /** @var User $actor */
        $actor = $request->user();
        $setUserActive->handle($actor, $user, false);

        return back()->with('status', "{$user->name} was deactivated.");
    }
}
