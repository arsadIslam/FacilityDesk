<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Users\AcceptInvitation;
use App\Http\Controllers\Controller;
use App\Http\Requests\AcceptInvitationRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Both routes sit behind the "signed" middleware, so a link that was tampered
 * with or has expired gets a 403. On another tenant's subdomain the user does
 * not resolve at all, so that is a 404.
 */
final class InvitationController extends Controller
{
    public function show(User $user): View|RedirectResponse
    {
        if ($redirect = $this->rejectUsedInvitation($user)) {
            return $redirect;
        }

        return view('invitations.accept', ['user' => $user]);
    }

    public function store(AcceptInvitationRequest $request, User $user, AcceptInvitation $acceptInvitation): RedirectResponse
    {
        if ($redirect = $this->rejectUsedInvitation($user)) {
            return $redirect;
        }

        $acceptInvitation->handle($user, $request->string('password')->toString());

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('status', 'Welcome aboard! Your password is set.');
    }

    private function rejectUsedInvitation(User $user): ?RedirectResponse
    {
        if ($user->hasPendingInvitation() && $user->is_active) {
            return null;
        }

        return redirect()->route('login')->with('status', 'This invitation is no longer valid. Please sign in or ask your admin for a new one.');
    }
}
