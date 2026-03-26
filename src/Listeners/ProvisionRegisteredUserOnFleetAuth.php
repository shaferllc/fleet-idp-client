<?php

declare(strict_types=1);

namespace Fleet\IdpClient\Listeners;

use Fleet\IdpClient\FleetIdpOAuth;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Listens to Laravel's {@see Registered} and mirrors the new user to Fleet Auth
 * when fleet_idp.provisioning.token is set and a plain password is available on
 * the current request (see {@see \Fleet\IdpClient\Support\FleetProvisioningRequest}).
 */
class ProvisionRegisteredUserOnFleetAuth implements ShouldHandleEventsAfterCommit
{
    public function handle(Registered $event): void
    {
        $token = config('fleet_idp.provisioning.token');
        if (! is_string($token) || $token === '') {
            return;
        }

        $user = $event->user;
        if (! $user instanceof Model) {
            return;
        }

        $request = request();
        if ($request === null) {
            return;
        }

        $plain = $this->takePlainPasswordFromRequest($request);
        if (! is_string($plain) || $plain === '') {
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

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->asJson()
                ->withOptions(FleetIdpOAuth::redirectPreservingPostOptions())
                ->post($url, [
                    'name' => $user->getAttribute('name'),
                    'email' => $user->getAttribute('email'),
                    'password' => $plain,
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

    private function takePlainPasswordFromRequest(Request $request): ?string
    {
        /** @var array<int, string> $keys */
        $keys = config('fleet_idp.provisioning.password_request_keys', []);
        if ($keys === []) {
            $keys = ['_fleet_idp_provisioning_password', 'password', 'form.password'];
        }

        foreach ($keys as $key) {
            $value = data_get($request->all(), $key);
            if (! is_string($value) || $value === '') {
                continue;
            }

            $this->scrubMatchedPasswordFromRequest($request, $key);

            return $value;
        }

        return null;
    }

    private function scrubMatchedPasswordFromRequest(Request $request, string $matchedKey): void
    {
        $mergeKey = (string) config('fleet_idp.provisioning.merge_request_key', '_fleet_idp_provisioning_password');
        $request->request->remove($mergeKey);

        if ($matchedKey !== $mergeKey && ! str_contains($matchedKey, '.')) {
            $request->request->remove($matchedKey);
        }
    }
}
