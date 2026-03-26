<?php

declare(strict_types=1);

namespace Fleet\IdpClient\Http\Controllers;

use Fleet\IdpClient\FleetIdpEloquentUserProvisioner;
use Fleet\IdpClient\FleetIdpOAuth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

class FleetIdpOAuthWebController extends Controller
{
    public function redirect(Request $request): RedirectResponse|SymfonyRedirect
    {
        if (! FleetIdpOAuth::isConfigured()) {
            abort(404);
        }

        return redirect()->away(FleetIdpOAuth::authorizationRedirectUrl());
    }

    public function callback(Request $request): RedirectResponse
    {
        if (! FleetIdpOAuth::isConfigured()) {
            abort(404);
        }

        return match ((string) config('fleet_idp.web.mode', 'eloquent')) {
            'session' => $this->callbackSession($request),
            default => $this->callbackEloquent($request),
        };
    }

    protected function callbackEloquent(Request $request): RedirectResponse
    {
        if ($request->query('error')) {
            return $this->eloquentOAuthErrorRedirect(
                (string) $request->query('error_description', trans('fleet-idp::oauth.sign_in_cancelled')),
            );
        }

        $request->validate([
            'code' => ['required', 'string'],
            'state' => ['required', 'string'],
        ]);

        $stateKey = (string) config('fleet_idp.session_oauth_state_key');
        $expected = $request->session()->pull($stateKey);
        if (! is_string($expected) || ! hash_equals($expected, (string) $request->query('state'))) {
            return $this->eloquentOAuthErrorRedirect(trans('fleet-idp::oauth.invalid_state'));
        }

        try {
            $tokens = FleetIdpOAuth::exchangeCode((string) $request->query('code'));
            $remote = FleetIdpOAuth::fetchUser($tokens['access_token']);
        } catch (Throwable) {
            return $this->eloquentOAuthErrorRedirect(trans('fleet-idp::oauth.exchange_failed'));
        }

        $sync = FleetIdpEloquentUserProvisioner::syncFromRemoteUser($remote);
        $userModel = (string) config('fleet_idp.user_model');

        if ($sync['error'] !== null || ! $sync['user'] instanceof Model || ! is_a($sync['user'], $userModel, true)) {
            return $this->eloquentOAuthErrorRedirect(
                is_string($sync['error']) ? $sync['error'] : trans('fleet-idp::oauth.sync_failed'),
            );
        }

        $user = $sync['user'];

        if (method_exists($user, 'hasTwoFactorEnabled') && $user->hasTwoFactorEnabled()) {
            $twoFactorRoute = (string) config('fleet_idp.web.eloquent.two_factor_route', 'two-factor.challenge');
            $request->session()->put([
                'two_factor.id' => $user->getKey(),
                'two_factor.remember' => true,
            ]);
            $request->session()->regenerateToken();

            return redirect()->route($twoFactorRoute);
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        $postLogin = (string) config('fleet_idp.web.eloquent.post_login_route', 'dashboard');

        return redirect()->intended(route($postLogin, absolute: false));
    }

    protected function callbackSession(Request $request): RedirectResponse
    {
        $errorRoute = (string) config('fleet_idp.web.session.error_route', 'console.login');
        $errorKey = (string) config('fleet_idp.web.session.error_validation_key', 'password');

        if ($request->query('error')) {
            return redirect()->route($errorRoute)
                ->withErrors([
                    $errorKey => (string) $request->query('error_description', trans('fleet-idp::oauth.sign_in_cancelled')),
                ]);
        }

        $request->validate([
            'code' => ['required', 'string'],
            'state' => ['required', 'string'],
        ]);

        $stateKey = (string) config('fleet_idp.session_oauth_state_key');
        $expected = $request->session()->pull($stateKey);
        if (! is_string($expected) || ! hash_equals($expected, (string) $request->query('state'))) {
            return redirect()->route($errorRoute)
                ->withErrors([$errorKey => trans('fleet-idp::oauth.invalid_state')]);
        }

        try {
            $tokens = FleetIdpOAuth::exchangeCode((string) $request->query('code'));
            $user = FleetIdpOAuth::fetchUser($tokens['access_token']);
        } catch (Throwable) {
            return redirect()->route($errorRoute)
                ->withErrors([$errorKey => trans('fleet-idp::oauth.exchange_failed')]);
        }

        $authKey = (string) config('fleet_idp.web.session.auth_session_key', 'fleet_console_ok');
        $userKey = (string) config('fleet_idp.web.session.user_session_key', 'fleet_idp_user');

        $request->session()->put($authKey, true);
        $request->session()->put($userKey, [
            'id' => $user['id'] ?? null,
            'email' => $user['email'] ?? null,
            'name' => $user['name'] ?? null,
        ]);
        $request->session()->regenerate();

        $postLogin = (string) config('fleet_idp.web.session.post_login_route', 'console.dashboard');

        return redirect()->intended(route($postLogin, absolute: false));
    }

    protected function eloquentOAuthErrorRedirect(string $message): RedirectResponse
    {
        $route = (string) config('fleet_idp.web.eloquent.oauth_error_route', 'login');
        $sessionKey = (string) config('fleet_idp.web.eloquent.oauth_error_session_key', 'oauth_error');

        return redirect()->route($route)->with($sessionKey, $message);
    }
}
