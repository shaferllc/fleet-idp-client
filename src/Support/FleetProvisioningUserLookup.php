<?php

declare(strict_types=1);

namespace Fleet\IdpClient\Support;

use Fleet\IdpClient\FleetIdpOAuth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Server-to-server: asks Fleet Auth whether an email is registered (provisioning Bearer).
 */
final class FleetProvisioningUserLookup
{
    /**
     * @return bool|null True / false from Fleet, or null if unconfigured or request failed.
     */
    public static function emailExistsOnFleet(string $email): ?bool
    {
        $token = config('fleet_idp.provisioning.token');
        if (! is_string($token) || $token === '') {
            return null;
        }

        $url = self::lookupUrl();
        if ($url === null) {
            return null;
        }

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->asJson()
                ->withOptions(FleetIdpOAuth::redirectPreservingPostOptions())
                ->post($url, ['email' => $email]);

            if (! $response->successful()) {
                Log::warning('fleet_idp.provisioning.lookup_failed', [
                    'status' => $response->status(),
                ]);

                return null;
            }

            $exists = $response->json('exists');

            return is_bool($exists) ? $exists : null;
        } catch (\Throwable $e) {
            Log::warning('fleet_idp.provisioning.lookup_exception', [
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private static function lookupUrl(): ?string
    {
        $explicit = config('fleet_idp.provisioning.lookup_url');
        if (is_string($explicit) && trim($explicit) !== '') {
            return trim($explicit);
        }

        $usersUrl = config('fleet_idp.provisioning.url');
        if (is_string($usersUrl) && trim($usersUrl) !== '') {
            return rtrim(trim($usersUrl), '/').'/lookup';
        }

        $base = config('fleet_idp.url');
        if (is_string($base) && trim($base) !== '') {
            return rtrim(trim($base), '/').'/api/provisioning/users/lookup';
        }

        return null;
    }
}
