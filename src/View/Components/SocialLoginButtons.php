<?php

declare(strict_types=1);

namespace Fleet\IdpClient\View\Components;

use Closure;
use Fleet\IdpClient\FleetIdpOAuth;
use Fleet\IdpClient\Services\FleetSocialLoginPolicy;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class SocialLoginButtons extends Component
{
    public bool $githubEnabled;

    public bool $googleEnabled;

    public bool $fleetAuthEnabled;

    public bool $anyEnabled;

    public string $variant;

    public function __construct(string $variant = 'waypost')
    {
        $this->variant = $variant;
        $this->fleetAuthEnabled = FleetIdpOAuth::isConfigured();
        $this->githubEnabled = self::githubVisible();
        $this->googleEnabled = self::googleVisible();
        $this->anyEnabled = $this->githubEnabled || $this->googleEnabled || $this->fleetAuthEnabled;
    }

    public static function isEnabled(): bool
    {
        return self::githubVisible()
            || self::googleVisible()
            || FleetIdpOAuth::isConfigured();
    }

    private static function githubVisible(): bool
    {
        if (! (bool) config('fleet_idp.socialite.enabled', true)) {
            return false;
        }

        return self::servicesConfigured('github') && FleetSocialLoginPolicy::githubAllowed();
    }

    private static function googleVisible(): bool
    {
        if (! (bool) config('fleet_idp.socialite.enabled', true)) {
            return false;
        }

        return self::servicesConfigured('google') && FleetSocialLoginPolicy::googleAllowed();
    }

    private static function servicesConfigured(string $provider): bool
    {
        $config = config("services.{$provider}");

        return is_array($config)
            && filled($config['client_id'] ?? null)
            && filled($config['client_secret'] ?? null);
    }

    public function render(): View|Closure|string
    {
        return view('fleet-idp::components.social-login-buttons');
    }
}
