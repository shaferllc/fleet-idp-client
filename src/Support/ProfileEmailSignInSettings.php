<?php

declare(strict_types=1);

namespace Fleet\IdpClient\Support;

use Fleet\IdpClient\FleetIdp;
use Fleet\IdpClient\FleetIdpEmailLogin;
use Fleet\IdpClient\Services\FleetSocialLoginPolicy;
use Illuminate\Contracts\Auth\Authenticatable;

final class ProfileEmailSignInSettings
{
    /**
     * Whether the profile UI for email code / magic link should appear.
     * Hidden for org-managed accounts when the feature is unavailable and they have not already enabled it.
     */
    public static function showInProfile(?Authenticatable $user): bool
    {
        if ($user === null) {
            return false;
        }

        if (! FleetIdp::passwordManagedByIdp($user)) {
            return true;
        }

        if (EmailSignInUserOptions::userAllowsAnyPasswordlessEmail($user)) {
            return true;
        }

        return FleetIdpEmailLogin::isAvailable()
            && (FleetSocialLoginPolicy::emailLoginCodeAllowed()
                || FleetSocialLoginPolicy::emailLoginMagicLinkAllowed());
    }
}
