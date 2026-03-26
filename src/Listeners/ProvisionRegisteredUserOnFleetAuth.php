<?php

declare(strict_types=1);

namespace Fleet\IdpClient\Listeners;

use Fleet\IdpClient\Events\UserRegisteredForFleetProvisioning;
use Fleet\IdpClient\FleetIdpOAuth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProvisionRegisteredUserOnFleetAuth
{
    public function handle(UserRegisteredForFleetProvisioning $event): void
    {
        $token = config('fleet_idp.provisioning.token');
        if (! is_string($token) || $token === '') {
            return;
        }

        $url = config('fleet_idp.provisioning.url');
        if (! is_string($url) || trim($url) === '') {
            $base = config('fleet_idp.url');
            if (! is_string($base) || trim($base) === '') {
                Log::warning('fleet_idp.provisioning.skipped_missing_fleet_idp_url');

                return;
            }
            $url = rtrim($base, '/').'/api/provisioning/users';
        }

        $user = $event->user;

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->asJson()
                ->withOptions(FleetIdpOAuth::redirectPreservingPostOptions())
                ->post($url, [
                    'name' => $user->getAttribute('name'),
                    'email' => $user->getAttribute('email'),
                    'password' => $event->plainPassword,
                ]);

            if ($response->successful()) {
                return;
            }

            Log::warning('fleet_idp.provisioning.request_failed', [
                'status' => $response->status(),
                'email' => $user->getAttribute('email'),
            ]);
        } catch (\Throwable $e) {
            Log::warning('fleet_idp.provisioning.exception', [
                'message' => $e->getMessage(),
                'email' => $user->getAttribute('email'),
            ]);
        }
    }
}
