<?php

declare(strict_types=1);

namespace Fleet\IdpClient\View\Components;

use Fleet\IdpClient\FleetEmailSignIn;
use Fleet\IdpClient\FleetIdpEmailLogin;
use Fleet\IdpClient\FleetIdpOAuth;
use Fleet\IdpClient\Services\FleetSocialLoginPolicy;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Component;

/**
 * Social login buttons, optional passwordless card, and related dividers for satellite login pages.
 */
final class LoginScreenFleetSurfaces extends Component
{
    public bool $showPasswordlessCard;

    public string $guestEmailCodeRouteName;

    public function __construct(
        public string $variant = 'waypost',
        public bool $wireNavigate = false,
        public bool $showSessionAlerts = true,
    ) {
        $this->guestEmailCodeRouteName = (string) config('fleet_idp.email_sign_in.guest_email_code_route_name', 'login.email-code');

        $policyAllowsEmailLogin = FleetSocialLoginPolicy::emailLoginCodeAllowed()
            || FleetSocialLoginPolicy::emailLoginMagicLinkAllowed();

        $this->showPasswordlessCard = Route::has($this->guestEmailCodeRouteName)
            && FleetEmailSignIn::guestFlowAvailable()
            && (
                FleetSocialLoginPolicy::guestEmailLoginCardWithoutIdpDelivery()
                || ($policyAllowsEmailLogin && FleetIdpEmailLogin::isAvailable())
                || ($policyAllowsEmailLogin && FleetIdpOAuth::isConfigured())
                || filter_var(config('fleet_idp.email_sign_in.always_show_guest_card_on_login', false), FILTER_VALIDATE_BOOL)
            );
    }

    public function render(): View
    {
        return view('fleet-idp::components.login-screen-fleet-surfaces');
    }
}
