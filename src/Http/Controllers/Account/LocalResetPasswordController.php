<?php

declare(strict_types=1);

namespace Fleet\IdpClient\Http\Controllers\Account;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

final class LocalResetPasswordController extends Controller
{
    public function create(Request $request, string $token): View
    {
        $view = config('fleet_idp.account.views.reset_password');
        $view = is_string($view) && $view !== '' ? $view : 'fleet-idp::account.reset-password';

        return view($view, [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) use ($request): void {
                $user->forceFill([
                    'password' => Hash::make($request->string('password')->toString()),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        $loginRoute = (string) config('fleet_idp.account.after_reset_route', 'login');

        return $status === Password::PASSWORD_RESET
            ? redirect()->route($loginRoute)->with('status', __($status))
            : back()->withErrors(['email' => __($status)]);
    }
}
