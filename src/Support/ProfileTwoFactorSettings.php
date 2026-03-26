<?php

declare(strict_types=1);

namespace Fleet\IdpClient\Support;

use Fleet\IdpClient\Services\FleetSocialLoginPolicy;
use Illuminate\Contracts\Auth\Authenticatable;

final class ProfileTwoFactorSettings
{
    /**
     * Whether the profile card for TOTP two-factor should appear.
     */
    public static function showInProfile(?Authenticatable $user): bool
    {
        if ($user === null || ! self::userSupportsTwoFactorState($user)) {
            return false;
        }

        if ($user->hasTwoFactorEnabled() || $user->hasPendingTwoFactorSetup()) {
            return true;
        }

        return FleetSocialLoginPolicy::allowTwoFactor() || FleetSocialLoginPolicy::requireTwoFactor();
    }

    public static function mayBeginSetup(): bool
    {
        return FleetSocialLoginPolicy::allowTwoFactor() || FleetSocialLoginPolicy::requireTwoFactor();
    }

    public static function mayDisable(): bool
    {
        return ! FleetSocialLoginPolicy::requireTwoFactor();
    }

    private static function userSupportsTwoFactorState(Authenticatable $user): bool
    {
        return method_exists($user, 'hasTwoFactorEnabled')
            && method_exists($user, 'hasPendingTwoFactorSetup');
    }
}
