<?php

declare(strict_types=1);

namespace Fleet\IdpClient\Support;

use Fleet\IdpClient\FleetIdpOAuth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Server-to-server: asks Fleet Auth to send its password-reset email (provisioning Bearer).
 * Keeps the user on the satellite for the form submit; the email link targets Fleet Auth.
 */
final class FleetProvisioningPasswordReset
{
    /**
     * @return array{ok: bool, error: ?string, http_status: ?int}
     *                                                            error: missing_provisioning_token|missing_idp_url|unauthorized|service_unavailable|bad_response|http_error|exception
     */
    public static function attempt(string $email): array
    {
        $token = config('fleet_idp.provisioning.token');
        if (! is_string($token) || $token === '') {
            return ['ok' => false, 'error' => 'missing_provisioning_token', 'http_status' => null];
        }

        $url = self::passwordResetUrl();
        if ($url === null) {
            return ['ok' => false, 'error' => 'missing_idp_url', 'http_status' => null];
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
                ->post($url, ['email' => $email]);

            $status = $response->status();

            if ($status === 401) {
                return ['ok' => false, 'error' => 'unauthorized', 'http_status' => 401];
            }

            if ($status === 503) {
                return ['ok' => false, 'error' => 'service_unavailable', 'http_status' => 503];
            }

            if (! $response->successful()) {
                Log::warning('fleet_idp.provisioning.password_reset_failed', [
                    'status' => $status,
                    'url' => $url,
                ]);

                return ['ok' => false, 'error' => 'http_error', 'http_status' => $status];
            }

            $bodyStatus = $response->json('status');

            if ($bodyStatus === 'accepted') {
                return ['ok' => true, 'error' => null, 'http_status' => $status];
            }

            return ['ok' => false, 'error' => 'bad_response', 'http_status' => $status];
        } catch (\Throwable $e) {
            Log::warning('fleet_idp.provisioning.password_reset_exception', [
                'message' => $e->getMessage(),
                'url' => $url,
            ]);

            return ['ok' => false, 'error' => 'exception', 'http_status' => null];
        }
    }

    public static function request(string $email): bool
    {
        return self::attempt($email)['ok'];
    }

    private static function passwordResetUrl(): ?string
    {
        $explicit = config('fleet_idp.provisioning.password_reset_url');
        if (is_string($explicit) && trim($explicit) !== '') {
            return trim($explicit);
        }

        $usersUrl = config('fleet_idp.provisioning.url');
        if (is_string($usersUrl) && trim($usersUrl) !== '') {
            return rtrim(trim($usersUrl), '/').'/password-reset';
        }

        $base = config('fleet_idp.url');
        if (is_string($base) && trim($base) !== '') {
            return rtrim(trim($base), '/').'/api/provisioning/users/password-reset';
        }

        return null;
    }
}
