<?php

declare(strict_types=1);

namespace Fleet\IdpClient\Testing;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

/**
 * Reuse across satellite apps’ Feature tests for forgot-password + Fleet lookup.
 */
trait InteractsWithFleetIdpPasswordReset
{
    protected function configureFleetIdpWithProvisioningLookup(
        string $fleetBaseUrl = 'https://fleet.example.test',
        string $provisioningToken = 'provision-secret',
    ): void {
        Config::set('fleet_idp.url', $fleetBaseUrl);
        Config::set('fleet_idp.account.local_password_only', false);
        Config::set('fleet_idp.provisioning.token', $provisioningToken);
    }

    protected function fakeFleetProvisioningUserLookup(bool $exists): void
    {
        $base = rtrim((string) config('fleet_idp.url'), '/');
        Http::fake([
            $base.'/api/provisioning/users/lookup' => Http::response(['exists' => $exists], 200),
        ]);
    }

    protected function fakeFleetProvisioningPasswordReset(bool $success = true): void
    {
        $base = rtrim((string) config('fleet_idp.url'), '/');
        Http::fake([
            $base.'/api/provisioning/users/password-reset' => $success
                ? Http::response(['status' => 'accepted'], 200)
                : Http::response(['message' => 'error'], 500),
        ]);
    }
}
