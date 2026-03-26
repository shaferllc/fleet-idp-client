<?php

declare(strict_types=1);

namespace Fleet\IdpClient\Support;

use Fleet\IdpClient\Contracts\EmailSignInSessionCompleter;
use Fleet\IdpClient\Services\FleetSocialLoginPolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * Default session completion for satellite apps (matches typical Breeze / Fortify wiring).
 * Bind {@see EmailSignInSessionCompleter} to override.
 */
final class DefaultEmailSignInSessionCompleter implements EmailSignInSessionCompleter
{
    public function complete(Model $user, bool $remember): array
    {
        if (method_exists($user, 'hasTwoFactorEnabled') && $user->hasTwoFactorEnabled()
            && FleetSocialLoginPolicy::respectLocalTotpForSessions()) {
            Session::put([
                (string) config('fleet_idp.socialite.two_factor_session_user_id_key', 'two_factor.id') => $user->getKey(),
                (string) config('fleet_idp.socialite.two_factor_session_remember_key', 'two_factor.remember') => $remember,
            ]);
            Session::regenerateToken();

            return [
                'mode' => 'two_factor',
                'url' => route((string) config('fleet_idp.web.eloquent.two_factor_route', 'two-factor.challenge'), absolute: false),
            ];
        }

        Auth::login($user, $remember);
        Session::regenerate();

        return [
            'mode' => 'session',
            'url' => route((string) config('fleet_idp.web.eloquent.post_login_route', 'dashboard'), absolute: false),
        ];
    }
}
