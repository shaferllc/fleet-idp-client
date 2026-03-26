<?php

declare(strict_types=1);

namespace Fleet\IdpClient\Support;

use Fleet\IdpClient\FleetIdpEmailLogin;
use Fleet\IdpClient\Services\FleetSocialLoginPolicy;

/**
 * Resolves which user model attributes gate email code vs magic-link sign-in.
 *
 * When {@see config('fleet_idp.email_sign_in.user_code_enabled_attribute')} (or magic) is null,
 * both fall back to {@see config('fleet_idp.email_sign_in.user_enabled_attribute')} (legacy single flag).
 */
final class EmailSignInUserOptions
{
    public static function codeEnabledAttribute(): string
    {
        $v = config('fleet_idp.email_sign_in.user_code_enabled_attribute');
        if (is_string($v) && $v !== '') {
            return $v;
        }

        return (string) config('fleet_idp.email_sign_in.user_enabled_attribute', 'email_code_login_enabled');
    }

    public static function magicLinkEnabledAttribute(): string
    {
        $v = config('fleet_idp.email_sign_in.user_magic_link_enabled_attribute');
        if (is_string($v) && $v !== '') {
            return $v;
        }

        return (string) config('fleet_idp.email_sign_in.user_enabled_attribute', 'email_code_login_enabled');
    }

    public static function userAllowsCode(object $user): bool
    {
        return filter_var(data_get($user, self::codeEnabledAttribute(), false), FILTER_VALIDATE_BOOLEAN);
    }

    public static function userAllowsMagicLink(object $user): bool
    {
        return filter_var(data_get($user, self::magicLinkEnabledAttribute(), false), FILTER_VALIDATE_BOOLEAN);
    }

    public static function userAllowsAnyPasswordlessEmail(object $user): bool
    {
        return self::userAllowsCode($user) || self::userAllowsMagicLink($user);
    }

    /**
     * What to show on the guest email sign-in page when Fleet password client is configured (org policy caps options).
     */
    public static function loginPageOffersCode(): bool
    {
        if (! FleetIdpEmailLogin::isAvailable()) {
            return true;
        }

        return FleetSocialLoginPolicy::emailLoginCodeAllowed();
    }

    public static function loginPageOffersMagicLink(): bool
    {
        if (! FleetIdpEmailLogin::isAvailable()) {
            return true;
        }

        return FleetSocialLoginPolicy::emailLoginMagicLinkAllowed();
    }
}
