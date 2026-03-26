<?php

declare(strict_types=1);

namespace Fleet\IdpClient\Support;

use Fleet\IdpClient\FleetIdp;
use Fleet\IdpClient\FleetIdpOAuth;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Route;

/**
 * Whether the profile card for linking / syncing with Fleet Auth should appear.
 */
final class ProfileFleetAccountSettings
{
    public static function showInProfile(?Authenticatable $user): bool
    {
        if ($user === null) {
            return false;
        }

        if (FleetIdp::passwordManagedByIdp($user)) {
            return false;
        }

        if (FleetPasswordRouting::idpBaseUrl() === '') {
            return false;
        }

        $redirectName = (string) config('fleet_idp.web.route_names.redirect', 'fleet-idp.oauth.redirect');
        $oauthReady = FleetIdpOAuth::isConfigured() && Route::has($redirectName);

        $token = config('fleet_idp.provisioning.token');
        $provReady = is_string($token) && trim($token) !== '';

        $pwd = $user->getAuthPassword();
        $hasLocalPassword = is_string($pwd) && $pwd !== '';

        return $oauthReady || ($provReady && $hasLocalPassword);
    }

    public static function oauthLinkAvailable(): bool
    {
        $redirectName = (string) config('fleet_idp.web.route_names.redirect', 'fleet-idp.oauth.redirect');

        return FleetIdpOAuth::isConfigured() && Route::has($redirectName);
    }

    public static function passwordSyncAvailable(): bool
    {
        $token = config('fleet_idp.provisioning.token');

        return is_string($token) && trim($token) !== '';
    }
}
