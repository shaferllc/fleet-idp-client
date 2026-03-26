<?php

declare(strict_types=1);

namespace Fleet\IdpClient\Http\Controllers;

use Fleet\IdpClient\FleetIdpEloquentUserProvisioner;
use Fleet\IdpClient\FleetIdpOAuth;
use Fleet\IdpClient\Services\FleetSocialLoginPolicy;
use Fleet\IdpClient\InvalidRedirectUriConfig;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

class FleetIdpOAuthWebController extends Controller
{
    public function redirect(Request $request): RedirectResponse|SymfonyRedirect
    {
        if (! FleetIdpOAuth::isConfigured()) {
            return $this->oauthFailureRedirect(trans('fleet-idp::oauth.not_configured'));
        }

        try {
            $url = FleetIdpOAuth::authorizationRedirectUrl();
        } catch (InvalidRedirectUriConfig $e) {
            return $this->oauthFailureRedirect($e->getMessage());
        } catch (Throwable $e) {
            Log::warning('fleet_idp.oauth.start_failed', [
                'message' => $e->getMessage(),
            ]);

            return $this->oauthFailureRedirect(trans('fleet-idp::oauth.start_failed'));
        }

        return redirect()->away($url);
    }

    public function failure(Request $request): View
    {
        $sessionKey = (string) config('fleet_idp.web.eloquent.oauth_error_session_key', 'oauth_error');
        $message = $request->session()->pull($sessionKey);

        if (! is_string($message) || trim($message) === '') {
            $message = trans('fleet-idp::oauth.failure_generic');
        }

        $tryAgainRoute = (string) config('fleet_idp.web.eloquent.try_again_route', 'login');
        $tryAgainUrl = Route::has($tryAgainRoute)
            ? route($tryAgainRoute, absolute: true)
            : url('/');

        return view('fleet-idp::oauth-failure', [
            'message' => $message,
            'tryAgainUrl' => $tryAgainUrl,
        ]);
    }

    public function callback(Request $request): RedirectResponse
    {
        if (! FleetIdpOAuth::isConfigured()) {
            return $this->oauthFailureRedirect(trans('fleet-idp::oauth.not_configured'));
        }

        return match ((string) config('fleet_idp.web.mode', 'eloquent')) {
            'session' => $this->callbackSession($request),
            default => $this->callbackEloquent($request),
        };
    }

    protected function callbackEloquent(Request $request): RedirectResponse
    {
        if ($request->query('error')) {
            Log::warning('fleet_idp.oauth.idp_error', [
                'error' => (string) $request->query('error'),
                'error_description' => (string) $request->query('error_description', ''),
            ]);

            return $this->oauthFailureRedirect(
                (string) $request->query('error_description', trans('fleet-idp::oauth.sign_in_cancelled')),
            );
        }

        try {
            $request->validate([
                'code' => ['required', 'string'],
                'state' => ['required', 'string'],
            ]);
        } catch (ValidationException) {
            return $this->oauthFailureRedirect(trans('fleet-idp::oauth.callback_missing_params'));
        }

        $stateKey = (string) config('fleet_idp.session_oauth_state_key');
        $expected = $request->session()->pull($stateKey);
        if (! is_string($expected) || ! hash_equals($expected, (string) $request->query('state'))) {
            Log::warning('fleet_idp.oauth.invalid_state', [
                'had_session_state' => is_string($expected),
            ]);

            return $this->oauthFailureRedirect(trans('fleet-idp::oauth.invalid_state'));
        }

        try {
            $tokens = FleetIdpOAuth::exchangeCode((string) $request->query('code'));
            $remote = FleetIdpOAuth::fetchUser($tokens['access_token']);
        } catch (InvalidRedirectUriConfig $e) {
            return $this->oauthFailureRedirect($e->getMessage());
        } catch (Throwable $e) {
            Log::warning('fleet_idp.oauth.exchange_failed', [
                'message' => $e->getMessage(),
            ]);

            return $this->oauthFailureRedirect($this->friendlyFleetAuthCallbackError($e));
        }

        $sync = FleetIdpEloquentUserProvisioner::syncFromRemoteUser($remote);
        $userModel = (string) config('fleet_idp.user_model');

        if ($sync['error'] !== null || ! $sync['user'] instanceof Model || ! is_a($sync['user'], $userModel, true)) {
            Log::warning('fleet_idp.oauth.user_sync_failed', [
                'error' => $sync['error'],
            ]);

            return $this->oauthFailureRedirect(
                is_string($sync['error']) ? $sync['error'] : trans('fleet-idp::oauth.sync_failed'),
            );
        }

        $user = $sync['user'];

        if (method_exists($user, 'hasTwoFactorEnabled') && $user->hasTwoFactorEnabled()
            && FleetSocialLoginPolicy::respectLocalTotpForSessions()) {
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

        try {
            $request->validate([
                'code' => ['required', 'string'],
                'state' => ['required', 'string'],
            ]);
        } catch (ValidationException) {
            return redirect()->route($errorRoute)
                ->withErrors([$errorKey => trans('fleet-idp::oauth.callback_missing_params')]);
        }

        $stateKey = (string) config('fleet_idp.session_oauth_state_key');
        $expected = $request->session()->pull($stateKey);
        if (! is_string($expected) || ! hash_equals($expected, (string) $request->query('state'))) {
            return redirect()->route($errorRoute)
                ->withErrors([$errorKey => trans('fleet-idp::oauth.invalid_state')]);
        }

        try {
            $tokens = FleetIdpOAuth::exchangeCode((string) $request->query('code'));
            $user = FleetIdpOAuth::fetchUser($tokens['access_token']);
        } catch (Throwable $e) {
            Log::warning('fleet_idp.oauth.exchange_failed', [
                'message' => $e->getMessage(),
            ]);

            return redirect()->route($errorRoute)
                ->withErrors([$errorKey => $this->friendlyFleetAuthCallbackError($e)]);
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

    /**
     * Show Passport / IdP error text when present; otherwise a generic line (and details if APP_DEBUG).
     */
    protected function friendlyFleetAuthCallbackError(Throwable $e): string
    {
        $m = $e->getMessage();
        if (str_starts_with($m, 'Fleet Auth')) {
            return $m;
        }

        if (config('app.debug')) {
            return trans('fleet-idp::oauth.exchange_failed').' '.$m;
        }

        return trans('fleet-idp::oauth.exchange_failed');
    }

    protected function oauthFailureRedirect(string $message): RedirectResponse
    {
        Log::notice('fleet_idp.oauth.client_redirect_error', ['message' => $message]);

        if ((string) config('fleet_idp.web.mode', 'eloquent') === 'session') {
            $errorRoute = (string) config('fleet_idp.web.session.error_route', 'console.login');
            $errorKey = (string) config('fleet_idp.web.session.error_validation_key', 'password');

            return redirect()->route($errorRoute)
                ->withErrors([$errorKey => $message]);
        }

        $route = (string) config('fleet_idp.web.eloquent.oauth_error_route', 'fleet-idp.oauth.failure');
        $sessionKey = (string) config('fleet_idp.web.eloquent.oauth_error_session_key', 'oauth_error');

        if (! Route::has($route)) {
            $fallback = Route::has('login') ? route('login', absolute: true) : url('/');

            return redirect()->to($fallback)->with($sessionKey, $message);
        }

        return redirect()->route($route)->with($sessionKey, $message);
    }
}
