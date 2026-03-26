<?php

declare(strict_types=1);

namespace Fleet\IdpClient;

use Fleet\IdpClient\Services\FleetSocialLoginPolicy;
use Fleet\IdpClient\Services\LocalEmailLoginService;
use Fleet\IdpClient\Support\EmailSignInUserOptions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Passwordless email sign-in: Fleet-linked users use Fleet Auth APIs when allowed;
 * others use {@see LocalEmailLoginService} on this satellite.
 *
 * Opt-in per user via {@see config('fleet_idp.email_sign_in.user_enabled_attribute')}.
 */
final class FleetEmailSignIn
{
    /**
     * Guests may open the email sign-in UI; codes/links are only sent for opted-in accounts.
     */
    public static function guestFlowAvailable(): bool
    {
        return true;
    }

    public static function offersFleetCode(): bool
    {
        return FleetIdpEmailLogin::isAvailable() && FleetSocialLoginPolicy::emailLoginCodeAllowed();
    }

    public static function offersFleetMagicLink(): bool
    {
        return FleetIdpEmailLogin::isAvailable() && FleetSocialLoginPolicy::emailLoginMagicLinkAllowed();
    }

    public static function offersLocalMagicLink(): bool
    {
        return true;
    }

    public static function offersLocalCode(): bool
    {
        return true;
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    public static function send(string $email, string $delivery): array
    {
        $email = Str::lower(trim($email));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => true];
        }

        $user = static::findUserByEmail($email);
        if ($user === null) {
            return ['ok' => true];
        }

        if ($delivery === 'code' && ! EmailSignInUserOptions::userAllowsCode($user)) {
            return ['ok' => true];
        }

        if ($delivery === 'magic_link' && ! EmailSignInUserOptions::userAllowsMagicLink($user)) {
            return ['ok' => true];
        }

        if ($deny = self::policyDeniesDelivery($delivery)) {
            return $deny;
        }

        if (FleetIdp::passwordManagedByIdp($user)) {
            if (! FleetIdpEmailLogin::isAvailable()) {
                return [
                    'ok' => false,
                    'error' => __('Central sign-in is not configured. Use password login or contact support.'),
                ];
            }

            $ok = $delivery === 'code'
                ? FleetIdpEmailLogin::sendCode($user->email)
                : FleetIdpEmailLogin::sendMagicLink($user->email);

            return $ok
                ? ['ok' => true]
                : ['ok' => false, 'error' => __('Could not send the sign-in message. Try again later.')];
        }

        $local = app(LocalEmailLoginService::class);
        $ok = $delivery === 'code'
            ? $local->sendCode($user)
            : $local->sendMagicLink($user);

        return $ok
            ? ['ok' => true]
            : ['ok' => false, 'error' => __('Could not send the sign-in message. Try again later.')];
    }

    public static function verifyCode(string $email, string $code): ?Model
    {
        $email = Str::lower(trim($email));
        $user = static::findUserByEmail($email);
        if ($user === null || ! EmailSignInUserOptions::userAllowsCode($user)) {
            return null;
        }

        if (FleetIdp::passwordManagedByIdp($user)) {
            if (! FleetIdpEmailLogin::isAvailable()) {
                return null;
            }

            $remote = FleetIdpEmailLogin::verifyCode($email, $code);
            if ($remote === null) {
                return null;
            }

            $sync = FleetIdpEloquentUserProvisioner::syncFromRemoteUser($remote);
            if ($sync['error'] !== null || $sync['user'] === null) {
                return null;
            }

            return $sync['user'];
        }

        return app(LocalEmailLoginService::class)->verifyCode($email, $code);
    }

    public static function loginPageOffersCode(): bool
    {
        return EmailSignInUserOptions::loginPageOffersCode();
    }

    public static function loginPageOffersMagicLink(): bool
    {
        return EmailSignInUserOptions::loginPageOffersMagicLink();
    }

    private static function findUserByEmail(string $email): ?Model
    {
        $class = (string) config('fleet_idp.user_model', 'App\\Models\\User');
        /** @var class-string<Model> $class */

        return $class::query()->where('email', $email)->first();
    }

    /**
     * When the satellite is wired to Fleet (password grant), org policy applies to every account,
     * including satellite-only users who receive mail via {@see LocalEmailLoginService}.
     *
     * @return array{ok: false, error: string}|null
     */
    private static function policyDeniesDelivery(string $delivery): ?array
    {
        if (! FleetIdpEmailLogin::isAvailable()) {
            return null;
        }

        if ($delivery === 'code' && ! FleetSocialLoginPolicy::emailLoginCodeAllowed()) {
            return [
                'ok' => false,
                'error' => __('Your organization has not enabled email codes for this app. Ask a Fleet admin or use password login.'),
            ];
        }

        if ($delivery === 'magic_link' && ! FleetSocialLoginPolicy::emailLoginMagicLinkAllowed()) {
            return [
                'ok' => false,
                'error' => __('Your organization has not enabled magic links for this app. Ask a Fleet admin or use password login.'),
            ];
        }

        return null;
    }
}
