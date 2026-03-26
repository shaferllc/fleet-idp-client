<?php

declare(strict_types=1);

namespace Fleet\IdpClient\Http\Controllers;

use Fleet\IdpClient\Services\FleetSocialiteUserProvisioner;
use Fleet\IdpClient\Services\FleetSocialLoginPolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

class SocialiteOAuthController extends Controller
{
    /** @var list<string> */
    private const Providers = ['github', 'google'];

    public function redirect(string $provider): RedirectResponse|SymfonyRedirect
    {
        $provider = $this->validatedProvider($provider);

        if (! $this->providerIsUsable($provider)) {
            abort(404);
        }

        return Socialite::driver($provider)->redirect();
    }

    public function callback(string $provider): RedirectResponse
    {
        $provider = $this->validatedProvider($provider);

        if (! $this->providerIsUsable($provider)) {
            abort(404);
        }

        $errorRoute = (string) config('fleet_idp.socialite.error_route', 'login');
        $errorKey = (string) config('fleet_idp.socialite.oauth_error_session_key', 'oauth_error');

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (Throwable) {
            return redirect()->route($errorRoute)
                ->with($errorKey, __('fleet-idp::socialite.sign_in_failed'));
        }

        try {
            $user = FleetSocialiteUserProvisioner::resolve($provider, $socialUser);
        } catch (Throwable $e) {
            if ($e->getMessage() === 'missing_email') {
                return redirect()->route($errorRoute)
                    ->with($errorKey, __('fleet-idp::socialite.missing_email'));
            }

            if ($e->getMessage() === 'email_provider_conflict') {
                return redirect()->route($errorRoute)
                    ->with($errorKey, __('fleet-idp::socialite.email_provider_conflict'));
            }

            throw $e;
        }

        assert($user instanceof Model);

        if (method_exists($user, 'hasTwoFactorEnabled') && $user->hasTwoFactorEnabled()) {
            $tfUserId = (string) config('fleet_idp.socialite.two_factor_session_user_id_key', 'two_factor.id');
            $tfRemember = (string) config('fleet_idp.socialite.two_factor_session_remember_key', 'two_factor.remember');
            $tfRoute = (string) config('fleet_idp.socialite.two_factor_route', 'two-factor.challenge');

            request()->session()->put([
                $tfUserId => $user->getAuthIdentifier(),
                $tfRemember => true,
            ]);
            request()->session()->regenerateToken();

            return redirect()->route($tfRoute);
        }

        Auth::login($user, remember: true);
        request()->session()->regenerate();

        $postLogin = (string) config('fleet_idp.socialite.post_login_route', 'dashboard');

        return redirect()->intended(route($postLogin, absolute: false));
    }

    private function validatedProvider(string $provider): string
    {
        $provider = Str::lower($provider);
        if (! in_array($provider, self::Providers, true)) {
            abort(404);
        }

        return $provider;
    }

    private function providerIsUsable(string $provider): bool
    {
        if (! (bool) config('fleet_idp.socialite.enabled', true)) {
            return false;
        }

        if (! $this->servicesConfigured($provider)) {
            return false;
        }

        return $provider === 'github'
            ? FleetSocialLoginPolicy::githubAllowed()
            : FleetSocialLoginPolicy::googleAllowed();
    }

    private function servicesConfigured(string $provider): bool
    {
        $config = config("services.{$provider}");

        return is_array($config)
            && filled($config['client_id'] ?? null)
            && filled($config['client_secret'] ?? null);
    }
}
