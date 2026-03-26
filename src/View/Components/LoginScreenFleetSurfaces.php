<?php

declare(strict_types=1);

namespace Fleet\IdpClient\View\Components;

use Fleet\IdpClient\FleetEmailSignIn;
use Fleet\IdpClient\FleetIdpEmailLogin;
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
        public ?bool $loginCardWithoutFleetDelivery = null,
    ) {
        $this->guestEmailCodeRouteName = (string) config('fleet_idp.email_sign_in.guest_email_code_route_name', 'login.email-code');

        $withoutFleet = $this->loginCardWithoutFleetDelivery !== null
            ? $this->loginCardWithoutFleetDelivery
            : filter_var(config('fleet_idp.email_sign_in.login_card_without_fleet_delivery', false), FILTER_VALIDATE_BOOL);

        $this->showPasswordlessCard = Route::has($this->guestEmailCodeRouteName)
            && FleetEmailSignIn::guestFlowAvailable()
            && (
                (FleetIdpEmailLogin::isAvailable()
                    && (FleetSocialLoginPolicy::emailLoginCodeAllowed()
                        || FleetSocialLoginPolicy::emailLoginMagicLinkAllowed()))
                || $withoutFleet
            );
    }

    public function render(): View
    {
        return view('fleet-idp::components.login-screen-fleet-surfaces');
    }
}
