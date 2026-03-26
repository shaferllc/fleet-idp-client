<?php

declare(strict_types=1);

namespace Fleet\IdpClient\Support;

/**
 * Stash the plain password on the current HTTP request so
 * {@see \Fleet\IdpClient\Listeners\ProvisionRegisteredUserOnFleetAuth} can read it
 * when handling {@see \Illuminate\Auth\Events\Registered} (Laravel does not include
 * the password on that event).
 *
 * Call immediately before hashing the password and creating the user, then fire
 * {@see \Illuminate\Auth\Events\Registered} as usual.
 */
class FleetProvisioningRequest
{
    public static function stashPasswordForRegisteredEvent(string $plainPassword): void
    {
        $key = (string) config('fleet_idp.provisioning.merge_request_key', '_fleet_idp_provisioning_password');

        request()->merge([$key => $plainPassword]);
    }
}
