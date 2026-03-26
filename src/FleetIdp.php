<?php

declare(strict_types=1);

namespace Fleet\IdpClient;

use Fleet\IdpClient\Support\FleetPasswordRouting;
use Fleet\IdpClient\Support\FleetProvisioningPasswordChange;
use Fleet\IdpClient\Support\FleetProvisioningPasswordReset;
use Fleet\IdpClient\Support\FleetProvisioningUserCreate;
use Fleet\IdpClient\Support\FleetProvisioningUserLookup;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Single entry point for satellites: password routing, Fleet URLs, provisioning lookup.
 *
 * Prefer this over pulling {@see FleetPasswordRouting} / {@see FleetProvisioningUserLookup}
 * directly in app code so call sites stay stable when internals move.
 */
final class FleetIdp
{
    public static function passwordManagedByIdp(?Authenticatable $user): bool
    {
        return FleetPasswordRouting::userPasswordManagedByIdp($user);
    }

    public static function idpBaseUrl(): string
    {
        return FleetPasswordRouting::idpBaseUrl();
    }

    public static function localPasswordOnly(): bool
    {
        return FleetPasswordRouting::localPasswordOnly();
    }

    public static function idpForgotPasswordUrlWithEmail(?string $email): string
    {
        return FleetPasswordRouting::idpForgotPasswordUrlWithEmail($email);
    }

    public static function idpChangePasswordUrl(): string
    {
        return FleetPasswordRouting::idpChangePasswordUrl();
    }

    /**
     * Route name for the package “change password” redirect route (satellite → Fleet or local form).
     */
    public static function changePasswordRouteName(): string
    {
        return (string) config('fleet_idp.account.route_names.change_show', 'fleet-idp.account.password.edit');
    }

    /**
     * @return bool|null True / false from Fleet Auth, null if unconfigured or request failed.
     */
    public static function emailExistsOnFleet(string $email): ?bool
    {
        return FleetProvisioningUserLookup::emailExistsOnFleet($email);
    }

    /**
     * Trigger Fleet Auth’s password-reset email via provisioning API (satellite stays on-app for the request).
     */
    public static function requestFleetPasswordReset(string $email): bool
    {
        return FleetProvisioningPasswordReset::request($email);
    }

    /**
     * Same as {@see requestFleetPasswordReset()} but returns HTTP diagnostics when the call fails.
     *
     * @return array{ok: bool, error: ?string, http_status: ?int}
     */
    public static function attemptFleetPasswordReset(string $email): array
    {
        return FleetProvisioningPasswordReset::attempt($email);
    }

    /**
     * Change password on Fleet Auth for a linked account (provisioning Bearer). On success,
     * update the satellite user’s password hash locally so password grant stays in sync.
     *
     * @return array{ok: bool, error: ?string, http_status: ?int, errors: array<string, array<int, string>>}
     */
    public static function attemptFleetPasswordChange(string $email, string $currentPassword, string $newPassword, string $newPasswordConfirmation): array
    {
        return FleetProvisioningPasswordChange::attempt($email, $currentPassword, $newPassword, $newPasswordConfirmation);
    }

    /**
     * Create or acknowledge the user on Fleet Auth via provisioning API (same as registration mirror).
     * Does not set satellite provider / provider_id; use OAuth “Continue with Fleet” to link the session.
     *
     * @return array{ok: bool, status: ?string, error: ?string, http_status: ?int}
     */
    public static function attemptProvisionUserToFleet(Model $user, string $plainPassword): array
    {
        return FleetProvisioningUserCreate::attempt($user, $plainPassword);
    }
}
