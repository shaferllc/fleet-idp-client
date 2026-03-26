<?php

declare(strict_types=1);

namespace Fleet\IdpClient\Support;

use Fleet\IdpClient\FleetIdpOAuth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Server-to-server: change a user’s password on Fleet Auth (provisioning Bearer).
 */
final class FleetProvisioningPasswordChange
{
    /**
     * @return array{ok: bool, error: ?string, http_status: ?int, errors: array<string, array<int, string>>}
     *                                                            error: missing_provisioning_token|missing_idp_url|unauthorized|service_unavailable|validation_failed|http_error|bad_response|exception
     */
    public static function attempt(string $email, string $currentPassword, string $newPassword, string $newPasswordConfirmation): array
    {
        $token = config('fleet_idp.provisioning.token');
        if (! is_string($token) || $token === '') {
            return ['ok' => false, 'error' => 'missing_provisioning_token', 'http_status' => null, 'errors' => []];
        }

        $url = self::passwordChangeUrl();
        if ($url === null) {
            return ['ok' => false, 'error' => 'missing_idp_url', 'http_status' => null, 'errors' => []];
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
                    'email' => $email,
                    'current_password' => $currentPassword,
                    'password' => $newPassword,
                    'password_confirmation' => $newPasswordConfirmation,
                ]);

            $status = $response->status();

            if ($status === 401) {
                return ['ok' => false, 'error' => 'unauthorized', 'http_status' => 401, 'errors' => []];
            }

            if ($status === 503) {
                return ['ok' => false, 'error' => 'service_unavailable', 'http_status' => 503, 'errors' => []];
            }

            if ($status === 422) {
                /** @var array<string, array<int, string>|string> $raw */
                $raw = $response->json('errors') ?? [];
                $errors = [];
                foreach ($raw as $key => $messages) {
                    $errors[$key] = is_array($messages) ? $messages : [(string) $messages];
                }

                return ['ok' => false, 'error' => 'validation_failed', 'http_status' => 422, 'errors' => $errors];
            }

            if (! $response->successful()) {
                Log::warning('fleet_idp.provisioning.password_change_failed', [
                    'status' => $status,
                    'url' => $url,
                ]);

                return ['ok' => false, 'error' => 'http_error', 'http_status' => $status, 'errors' => []];
            }

            $bodyStatus = $response->json('status');

            if ($bodyStatus === 'updated') {
                return ['ok' => true, 'error' => null, 'http_status' => $status, 'errors' => []];
            }

            return ['ok' => false, 'error' => 'bad_response', 'http_status' => $status, 'errors' => []];
        } catch (\Throwable $e) {
            Log::warning('fleet_idp.provisioning.password_change_exception', [
                'message' => $e->getMessage(),
                'url' => $url,
            ]);

            return ['ok' => false, 'error' => 'exception', 'http_status' => null, 'errors' => []];
        }
    }

    private static function passwordChangeUrl(): ?string
    {
        $explicit = config('fleet_idp.provisioning.password_change_url');
        if (is_string($explicit) && trim($explicit) !== '') {
            return trim($explicit);
        }

        $usersUrl = config('fleet_idp.provisioning.url');
        if (is_string($usersUrl) && trim($usersUrl) !== '') {
            return rtrim(trim($usersUrl), '/').'/password-change';
        }

        $base = config('fleet_idp.url');
        if (is_string($base) && trim($base) !== '') {
            return rtrim(trim($base), '/').'/api/provisioning/users/password-change';
        }

        return null;
    }
}
