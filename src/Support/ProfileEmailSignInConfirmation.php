<?php

declare(strict_types=1);

namespace Fleet\IdpClient\Support;

use Carbon\CarbonInterface;
use Fleet\IdpClient\Notifications\ConfirmProfileEmailCodeSignInNotification;
use Illuminate\Support\Carbon;
use Fleet\IdpClient\Notifications\ConfirmProfileMagicLinkSignInNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Stages and completes “confirm by email” for profile toggles (code + magic link).
 *
 * Column names default to magic_link_sign_in_pending_* / email_code_sign_in_pending_*;
 * override via config fleet_idp.email_sign_in.profile_confirm.columns.
 */
final class ProfileEmailSignInConfirmation
{
    /**
     * @return array{pending_hash: string, pending_expires: string, enabled: string}
     */
    public static function magicLinkConfirmationFieldMap(): array
    {
        return [
            'pending_hash' => self::magicPendingHashColumn(),
            'pending_expires' => self::magicPendingExpiresColumn(),
            'enabled' => EmailSignInUserOptions::magicLinkEnabledAttribute(),
        ];
    }

    /**
     * @return array{pending_hash: string, pending_expires: string, enabled: string}
     */
    public static function emailCodeConfirmationFieldMap(): array
    {
        return [
            'pending_hash' => self::codePendingHashColumn(),
            'pending_expires' => self::codePendingExpiresColumn(),
            'enabled' => EmailSignInUserOptions::codeEnabledAttribute(),
        ];
    }

    public static function magicLinkConfirmationPending(object $user): bool
    {
        return self::pendingIsActive(
            data_get($user, self::magicPendingHashColumn()),
            data_get($user, self::magicPendingExpiresColumn()),
        );
    }

    public static function emailCodeConfirmationPending(object $user): bool
    {
        return self::pendingIsActive(
            data_get($user, self::codePendingHashColumn()),
            data_get($user, self::codePendingExpiresColumn()),
        );
    }

    public static function sendMagicLinkConfirmationMail(Model $user): void
    {
        if (EmailSignInUserOptions::mutuallyExclusiveCodeAndMagic()) {
            self::setEmailCodeEnabledOnProfile($user, false);
            $user->refresh();
        }

        $plain = Str::random(64);

        $user->forceFill([
            EmailSignInUserOptions::magicLinkEnabledAttribute() => false,
            self::magicPendingHashColumn() => hash('sha256', $plain),
            self::magicPendingExpiresColumn() => now()->addHours(self::tokenTtlHours()),
        ])->save();

        $routeName = (string) config(
            'fleet_idp.email_sign_in.profile_confirm.route_names.magic',
            'profile.confirm-magic-sign-in'
        );
        $url = URL::route($routeName, ['token' => $plain], true);

        $user->notify(new ConfirmProfileMagicLinkSignInNotification($url));
    }

    public static function sendEmailCodeConfirmationMail(Model $user): void
    {
        if (EmailSignInUserOptions::mutuallyExclusiveCodeAndMagic()) {
            self::setMagicLinkEnabledOnProfile($user, false);
            $user->refresh();
        }

        $plain = Str::random(64);

        $user->forceFill([
            EmailSignInUserOptions::codeEnabledAttribute() => false,
            self::codePendingHashColumn() => hash('sha256', $plain),
            self::codePendingExpiresColumn() => now()->addHours(self::tokenTtlHours()),
        ])->save();

        $routeName = (string) config(
            'fleet_idp.email_sign_in.profile_confirm.route_names.code',
            'profile.confirm-email-code-sign-in'
        );
        $url = URL::route($routeName, ['token' => $plain], true);

        $user->notify(new ConfirmProfileEmailCodeSignInNotification($url));
    }

    public static function clearMagicLinkPending(Model $user): void
    {
        $user->forceFill([
            self::magicPendingHashColumn() => null,
            self::magicPendingExpiresColumn() => null,
        ])->save();
    }

    public static function clearEmailCodePending(Model $user): void
    {
        $user->forceFill([
            self::codePendingHashColumn() => null,
            self::codePendingExpiresColumn() => null,
        ])->save();
    }

    public static function setMagicLinkEnabledOnProfile(Model $user, bool $enabled): void
    {
        $user->forceFill([
            EmailSignInUserOptions::magicLinkEnabledAttribute() => $enabled,
            self::magicPendingHashColumn() => null,
            self::magicPendingExpiresColumn() => null,
        ])->save();
    }

    public static function setEmailCodeEnabledOnProfile(Model $user, bool $enabled): void
    {
        $user->forceFill([
            EmailSignInUserOptions::codeEnabledAttribute() => $enabled,
            self::codePendingHashColumn() => null,
            self::codePendingExpiresColumn() => null,
        ])->save();
    }

    /**
     * Apply profile email-link confirmation for one-time codes (POST interstitial).
     */
    public static function completeEmailCodeProfileConfirm(Model $user): void
    {
        $attrs = [
            EmailSignInUserOptions::codeEnabledAttribute() => true,
            self::codePendingHashColumn() => null,
            self::codePendingExpiresColumn() => null,
        ];

        if (EmailSignInUserOptions::mutuallyExclusiveCodeAndMagic()) {
            $attrs[EmailSignInUserOptions::magicLinkEnabledAttribute()] = false;
            $attrs[self::magicPendingHashColumn()] = null;
            $attrs[self::magicPendingExpiresColumn()] = null;
        }

        $user->forceFill($attrs)->save();
    }

    /**
     * Apply profile email-link confirmation for magic links (POST interstitial).
     */
    public static function completeMagicLinkProfileConfirm(Model $user): void
    {
        $attrs = [
            EmailSignInUserOptions::magicLinkEnabledAttribute() => true,
            self::magicPendingHashColumn() => null,
            self::magicPendingExpiresColumn() => null,
        ];

        if (EmailSignInUserOptions::mutuallyExclusiveCodeAndMagic()) {
            $attrs[EmailSignInUserOptions::codeEnabledAttribute()] = false;
            $attrs[self::codePendingHashColumn()] = null;
            $attrs[self::codePendingExpiresColumn()] = null;
        }

        $user->forceFill($attrs)->save();
    }

    private static function pendingIsActive(mixed $hash, mixed $expires): bool
    {
        if ($hash === null || $expires === null) {
            return false;
        }

        if ($expires instanceof CarbonInterface) {
            return $expires->isFuture();
        }

        if (is_string($expires) || is_numeric($expires)) {
            return now()->lt(Carbon::parse($expires));
        }

        return false;
    }

    private static function tokenTtlHours(): int
    {
        return max(1, (int) config('fleet_idp.email_sign_in.profile_confirm.token_ttl_hours', 24));
    }

    private static function magicPendingHashColumn(): string
    {
        return (string) config(
            'fleet_idp.email_sign_in.profile_confirm.columns.magic_pending_token_hash',
            'magic_link_sign_in_pending_token_hash'
        );
    }

    private static function magicPendingExpiresColumn(): string
    {
        return (string) config(
            'fleet_idp.email_sign_in.profile_confirm.columns.magic_pending_expires_at',
            'magic_link_sign_in_pending_expires_at'
        );
    }

    private static function codePendingHashColumn(): string
    {
        return (string) config(
            'fleet_idp.email_sign_in.profile_confirm.columns.code_pending_token_hash',
            'email_code_sign_in_pending_token_hash'
        );
    }

    private static function codePendingExpiresColumn(): string
    {
        return (string) config(
            'fleet_idp.email_sign_in.profile_confirm.columns.code_pending_expires_at',
            'email_code_sign_in_pending_expires_at'
        );
    }
}
