<?php

declare(strict_types=1);

namespace Fleet\IdpClient\Support;

use Fleet\IdpClient\FleetIdpOAuth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Server-to-server: create or acknowledge a user on Fleet Auth (provisioning Bearer).
 * Same contract as registration-time provisioning; does not set satellite {@see provider}/{@see provider_id}.
 */
final class FleetProvisioningUserCreate
{
    /**
     * @return array{ok: bool, status: ?string, error: ?string, http_status: ?int}
     *                                                            status: created|exists when ok
     *                                                            error: missing_provisioning_token|missing_idp_url|unauthorized|http_error|bad_response|exception
     */
    public static function attempt(Model $user, string $plainPassword): array
    {
        $token = config('fleet_idp.provisioning.token');
        if (! is_string($token) || $token === '') {
            return ['ok' => false, 'status' => null, 'error' => 'missing_provisioning_token', 'http_status' => null];
        }

        $plainPassword = trim($plainPassword);
        if ($plainPassword === '') {
            return ['ok' => false, 'status' => null, 'error' => 'missing_password', 'http_status' => null];
        }

        $url = self::usersProvisioningUrl();
        if ($url === null) {
            return ['ok' => false, 'status' => null, 'error' => 'missing_idp_url', 'http_status' => null];
        }

        $verifySsl = filter_var(config('fleet_idp.provisioning.verify_ssl', true), FILTER_VALIDATE_BOOL);
        $options = FleetIdpOAuth::redirectPreservingPostOptions();
        if (! $verifySsl) {
            $options['verify'] = false;
        }

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->asJson()
                ->withOptions($options)
                ->post($url, [
                    'name' => $user->getAttribute('name'),
                    'email' => $user->getAttribute('email'),
                    'password' => $plainPassword,
                ]);

            $status = $response->status();

            if ($status === 401) {
                return ['ok' => false, 'status' => null, 'error' => 'unauthorized', 'http_status' => 401];
            }

            if (! $response->successful()) {
                Log::warning('fleet_idp.provisioning.user_create_failed', [
                    'status' => $status,
                    'url' => $url,
                ]);

                return ['ok' => false, 'status' => null, 'error' => 'http_error', 'http_status' => $status];
            }

            $bodyStatus = $response->json('status');
            if ($bodyStatus === 'created' || $bodyStatus === 'exists') {
                return ['ok' => true, 'status' => (string) $bodyStatus, 'error' => null, 'http_status' => $status];
            }

            return ['ok' => false, 'status' => null, 'error' => 'bad_response', 'http_status' => $status];
        } catch (\Throwable $e) {
            Log::warning('fleet_idp.provisioning.user_create_exception', [
                'message' => $e->getMessage(),
                'url' => $url,
            ]);

            return ['ok' => false, 'status' => null, 'error' => 'exception', 'http_status' => null];
        }
    }

    private static function usersProvisioningUrl(): ?string
    {
        $url = config('fleet_idp.provisioning.url');
        if (is_string($url) && trim($url) !== '') {
            return trim($url);
        }

        $base = config('fleet_idp.url');
        if (! is_string($base) || trim($base) === '') {
            return null;
        }

        return rtrim($base, '/').'/api/provisioning/users';
    }
}
