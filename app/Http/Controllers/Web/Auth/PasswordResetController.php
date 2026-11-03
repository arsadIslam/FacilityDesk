<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

final class PasswordResetController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Always answers the same way so the form cannot be used to discover
     * which addresses have an account.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'string', 'email']]);

        Password::sendResetLink(['email' => Str::lower($request->string('email')->toString()), 'is_active' => true]);

        return back()->with('status', 'If that address has an account, we have emailed a password reset link.');
    }

    public function edit(Request $request, string $token): View
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset(
            [
                'email' => Str::lower($request->string('email')->toString()),
                'password' => $request->string('password')->toString(),
                'password_confirmation' => $request->string('password_confirmation')->toString(),
                'token' => $request->string('token')->toString(),
            ],
            function (User $user, string $password): void {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withInput($request->only('email'))->withErrors(['email' => __($status)]);
        }

        return redirect()->route('login')->with('status', __($status));
    }
}
