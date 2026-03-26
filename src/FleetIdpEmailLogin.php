<?php

declare(strict_types=1);

namespace Fleet\IdpClient;

use Fleet\IdpClient\Services\FleetSocialLoginPolicy;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Server-to-server email code / magic link sign-in against Fleet Auth
 * POST /api/email-login/send and /api/email-login/verify (password client credentials).
 */
final class FleetIdpEmailLogin
{
    public static function isAvailable(): bool
    {
        return FleetIdpPasswordGrant::isConfigured();
    }

    public static function isCodeDeliveryAllowed(): bool
    {
        return self::isAvailable() && FleetSocialLoginPolicy::emailLoginCodeAllowed();
    }

    public static function isMagicLinkDeliveryAllowed(): bool
    {
        return self::isAvailable() && FleetSocialLoginPolicy::emailLoginMagicLinkAllowed();
    }

    public static function magicLinkReturnUrl(): string
    {
        $explicit = config('fleet_idp.email_login.magic_return_url');
        if (is_string($explicit) && trim($explicit) !== '') {
            return rtrim(trim($explicit), '/');
        }

        $base = FleetIdpOAuth::applicationHttpRoot();
        $path = '/'.ltrim((string) config('fleet_idp.email_login.magic_return_path', '/login/email-magic'), '/');

        return rtrim($base, '/').$path;
    }

    public static function sendCode(string $email): bool
    {
        return self::postSend($email, 'code', null);
    }

    public static function sendMagicLink(string $email): bool
    {
        return self::postSend($email, 'magic_link', self::magicLinkReturnUrl());
    }

    private static function postSend(string $email, string $delivery, ?string $returnUrl): bool
    {
        if (! self::isAvailable()) {
            return false;
        }
        if ($delivery === 'code' && ! FleetSocialLoginPolicy::emailLoginCodeAllowed()) {
            return false;
        }
        if ($delivery === 'magic_link' && ! FleetSocialLoginPolicy::emailLoginMagicLinkAllowed()) {
            return false;
        }

        try {
            /** @var Response $response */
            $response = Http::asJson()
                ->acceptJson()
                ->timeout((int) config('fleet_idp.email_login.http_timeout_seconds', 10))
                ->withOptions(FleetIdpOAuth::redirectPreservingPostOptions())
                ->post(rtrim(FleetIdpOAuth::requireIdpRootUrl(), '/').'/api/email-login/send', [
                    'client_id' => config('fleet_idp.password_client_id'),
                    'client_secret' => config('fleet_idp.password_client_secret'),
                    'email' => $email,
                    'delivery' => $delivery,
                    'magic_link_return_url' => $returnUrl,
                ]);
        } catch (Throwable) {
            return false;
        }

        return $response->successful();
    }

    /**
     * @return array{id: int|string, name: string, email: string}|null
     */
    public static function verifyCode(string $email, string $code): ?array
    {
        return self::postVerify([
            'email' => $email,
            'code' => $code,
        ]);
    }

    /**
     * @return array{id: int|string, name: string, email: string}|null
     */
    public static function verifyToken(string $token): ?array
    {
        return self::postVerify([
            'token' => $token,
        ]);
    }

    /**
     * @param  array<string, string>  $payload
     * @return array{id: int|string, name: string, email: string}|null
     */
    private static function postVerify(array $payload): ?array
    {
        if (! self::isAvailable()) {
            return null;
        }

        $body = array_merge([
            'client_id' => config('fleet_idp.password_client_id'),
            'client_secret' => config('fleet_idp.password_client_secret'),
        ], $payload);

        try {
            /** @var Response $response */
            $response = Http::asJson()
                ->acceptJson()
                ->timeout((int) config('fleet_idp.email_login.http_timeout_seconds', 10))
                ->withOptions(FleetIdpOAuth::redirectPreservingPostOptions())
                ->post(rtrim(FleetIdpOAuth::requireIdpRootUrl(), '/').'/api/email-login/verify', $body);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        /** @var array<string, mixed> $data */
        $data = $response->json();
        if (! isset($data['id'], $data['email']) || ! is_string($data['email'])) {
            return null;
        }

        return [
            'id' => $data['id'],
            'name' => is_string($data['name'] ?? null) ? $data['name'] : '',
            'email' => $data['email'],
        ];
    }
}
