<?php

namespace Fleet\IdpClient;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class FleetIdpPasswordGrant
{
    public static function isConfigured(): bool
    {
        $id = config('fleet_idp.password_client_id');
        $secret = config('fleet_idp.password_client_secret');
        $userModel = config('fleet_idp.user_model');

        if (! is_string($id) || $id === ''
            || ! is_string($secret) || $secret === ''
            || ! is_string($userModel) || $userModel === '') {
            return false;
        }

        try {
            FleetIdpOAuth::requireIdpRootUrl();
        } catch (RuntimeException) {
            return false;
        }

        return true;
    }

    /**
     * Validate credentials against the Fleet IdP and return a provisioned local user.
     */
    public static function attempt(string $email, string $password): ?Model
    {
        if (! self::isConfigured()) {
            return null;
        }

        try {
            /** @var Response $response */
            $response = Http::asForm()
                ->acceptJson()
                ->withOptions(FleetIdpOAuth::redirectPreservingPostOptions())
                ->post(FleetIdpOAuth::requireIdpRootUrl().'/oauth/token', [
                    'grant_type' => 'password',
                    'username' => $email,
                    'password' => $password,
                    'client_id' => config('fleet_idp.password_client_id'),
                    'client_secret' => config('fleet_idp.password_client_secret'),
                    'scope' => '',
                ]);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        /** @var string|null $access */
        $access = $response->json('access_token');
        if (! is_string($access) || $access === '') {
            return null;
        }

        try {
            $remote = FleetIdpOAuth::fetchUserWithToken($access);
        } catch (Throwable) {
            return null;
        }

        $sync = FleetIdpEloquentUserProvisioner::syncFromRemoteUser($remote);

        return $sync['user'];
    }
}
