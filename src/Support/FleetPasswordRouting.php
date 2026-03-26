<?php

declare(strict_types=1);

namespace Fleet\IdpClient\Support;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Decides whether password reset / change flows run on the IdP or locally on this app.
 */
final class FleetPasswordRouting
{
    public static function idpBaseUrl(): string
    {
        return rtrim((string) config('fleet_idp.url', ''), '/');
    }

    public static function localPasswordOnly(): bool
    {
        return filter_var(config('fleet_idp.account.local_password_only', false), FILTER_VALIDATE_BOOL);
    }

    public static function idpForgotPasswordUrl(): string
    {
        $path = '/'.trim((string) config('fleet_idp.account.idp_paths.forgot_password', '/forgot-password'), '/');

        return self::idpBaseUrl().$path;
    }

    /**
     * Fleet Auth forgot-password URL with optional email query (prefill when the IdP form supports it).
     */
    public static function idpForgotPasswordUrlWithEmail(?string $email): string
    {
        $url = self::idpForgotPasswordUrl();
        if (! is_string($email) || $email === '') {
            return $url;
        }
        $sep = str_contains($url, '?') ? '&' : '?';

        return $url.$sep.http_build_query(['email' => $email]);
    }

    public static function idpResetPasswordUrl(string $token, ?string $email): string
    {
        $pathTemplate = (string) config('fleet_idp.account.idp_paths.reset_password', '/reset-password/{token}');
        $path = str_replace('{token}', $token, $pathTemplate);
        $path = '/'.ltrim($path, '/');
        $url = self::idpBaseUrl().$path;
        if (is_string($email) && $email !== '') {
            $url .= '?'.http_build_query(['email' => $email]);
        }

        return $url;
    }

    public static function idpChangePasswordUrl(): string
    {
        $path = '/'.trim((string) config('fleet_idp.account.idp_paths.change_password', '/account/password'), '/');

        return self::idpBaseUrl().$path;
    }

    /**
     * Satellite user record is linked to Fleet (OAuth / password grant sync); password is authoritative on IdP.
     */
    public static function userPasswordManagedByIdp(?Authenticatable $user): bool
    {
        if ($user === null || self::localPasswordOnly() || self::idpBaseUrl() === '') {
            return false;
        }

        $provider = (string) config('fleet_idp.provider_name', 'fleet_auth');

        return (string) $user->getAttribute('provider') === $provider;
    }
}
