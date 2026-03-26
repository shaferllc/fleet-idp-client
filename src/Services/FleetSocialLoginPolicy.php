<?php

declare(strict_types=1);

namespace Fleet\IdpClient\Services;

use Fleet\IdpClient\FleetIdpOAuth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Reads Fleet Auth GET /api/social-login/providers so client apps only expose
 * GitHub/Google when the IdP allows it, and can read per-site 2FA / email-login flags.
 *
 * @phpstan-type PolicySnapshot array{
 *     github: bool,
 *     google: bool,
 *     allow_two_factor: bool,
 *     require_two_factor: bool,
 *     require_email_verification: bool,
 *     email_login_code: bool,
 *     email_login_magic_link: bool
 * }
 */
final class FleetSocialLoginPolicy
{
    /** @var PolicySnapshot|null */
    private static ?array $runtimeOverride = null;

    /**
     * @param  PolicySnapshot|null  $policy
     */
    public static function fake(?array $policy): void
    {
        self::$runtimeOverride = $policy;
    }

    public static function clearFake(): void
    {
        self::$runtimeOverride = null;
    }

    /**
     * Drop the cached GET /api/social-login/providers response (when policy_cache_seconds > 0).
     * Run after changing Integrations / OAuth flags on Fleet Auth if satellites should pick them up immediately.
     */
    public static function forgetCachedSnapshot(): void
    {
        $ttl = max(0, (int) config('fleet_idp.socialite.policy_cache_seconds', 60));
        if ($ttl === 0) {
            return;
        }

        if (! self::packageEnabled()) {
            return;
        }

        $base = rtrim((string) config('fleet_idp.url', ''), '/');
        if ($base === '') {
            return;
        }

        $url = self::providersRequestUrl();
        Cache::forget('fleet_idp.social_login_policy.'.md5($url));
    }

    public static function githubAllowed(): bool
    {
        return self::snapshot()['github'] ?? false;
    }

    public static function googleAllowed(): bool
    {
        return self::snapshot()['google'] ?? false;
    }

    /**
     * Satellite may offer optional TOTP (profile UI, login challenge when user opted in).
     */
    public static function allowTwoFactor(): bool
    {
        return self::snapshot()['allow_two_factor'] ?? true;
    }

    /**
     * Satellite must block app use until the user completes local two-factor setup.
     */
    public static function requireTwoFactor(): bool
    {
        return self::snapshot()['require_two_factor'] ?? false;
    }

    /**
     * Satellite must block app use until the user verifies their email address.
     */
    public static function requireEmailVerification(): bool
    {
        return self::snapshot()['require_email_verification'] ?? false;
    }

    /**
     * When false, ignore local TOTP for login / OAuth callback (org turned off optional 2FA).
     */
    public static function respectLocalTotpForSessions(): bool
    {
        return self::allowTwoFactor() || self::requireTwoFactor();
    }

    public static function emailLoginCodeAllowed(): bool
    {
        return self::snapshot()['email_login_code'] ?? false;
    }

    public static function emailLoginMagicLinkAllowed(): bool
    {
        return self::snapshot()['email_login_magic_link'] ?? false;
    }

    /**
     * @return PolicySnapshot
     */
    public static function snapshot(): array
    {
        if (self::$runtimeOverride !== null) {
            return self::normalizePolicy(self::$runtimeOverride);
        }

        if (! self::packageEnabled()) {
            return self::disabledSnapshot();
        }

        $base = rtrim((string) config('fleet_idp.url', ''), '/');
        if ($base === '') {
            return self::localDevSnapshot();
        }

        $ttl = max(0, (int) config('fleet_idp.socialite.policy_cache_seconds', 60));
        $url = self::providersRequestUrl();

        if ($ttl === 0) {
            return self::normalizePolicy(self::fetchOrFallback($url));
        }

        $cacheKey = 'fleet_idp.social_login_policy.'.md5($url);

        return Cache::remember($cacheKey, $ttl, static fn (): array => self::normalizePolicy(self::fetchOrFallback($url)));
    }

    private static function packageEnabled(): bool
    {
        return (bool) config('fleet_idp.socialite.enabled', true);
    }

    /**
     * @return PolicySnapshot
     */
    private static function disabledSnapshot(): array
    {
        return [
            'github' => false,
            'google' => false,
            'allow_two_factor' => true,
            'require_two_factor' => false,
            'require_email_verification' => false,
            'email_login_code' => false,
            'email_login_magic_link' => false,
        ];
    }

    /**
     * No IdP URL: do not block social buttons or satellite-only policy (fail-open for github/google).
     *
     * @return PolicySnapshot
     */
    private static function localDevSnapshot(): array
    {
        return [
            'github' => true,
            'google' => true,
            'allow_two_factor' => true,
            'require_two_factor' => false,
            'require_email_verification' => false,
            'email_login_code' => false,
            'email_login_magic_link' => false,
        ];
    }

    /**
     * Full URL for GET /api/social-login/providers, including ?client_id= when configured.
     */
    private static function providersRequestUrl(): string
    {
        $explicit = config('fleet_idp.socialite.providers_url');
        if (is_string($explicit) && trim($explicit) !== '') {
            $url = trim($explicit);
        } else {
            $base = rtrim((string) config('fleet_idp.url', ''), '/');
            $url = $base.'/api/social-login/providers';
        }

        $clientId = (string) config('fleet_idp.client_id', '');
        if ($clientId !== '') {
            $url .= (str_contains($url, '?') ? '&' : '?').'client_id='.rawurlencode($clientId);
        }

        return $url;
    }

    /**
     * @return array<string, mixed>
     */
    private static function fetchOrFallback(string $url): array
    {
        $failOpen = (bool) config('fleet_idp.socialite.policy_fail_open', true);

        try {
            $response = Http::timeout((int) config('fleet_idp.socialite.policy_timeout_seconds', 3))
                ->acceptJson()
                ->withOptions(FleetIdpOAuth::redirectPreservingPostOptions())
                ->get($url);
        } catch (\Throwable) {
            return self::rawFallbackArray($failOpen);
        }

        if (! $response->successful()) {
            return self::rawFallbackArray($failOpen);
        }

        /** @var array<string, mixed> $data */
        $data = $response->json();

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private static function rawFallbackArray(bool $failOpen): array
    {
        return [
            'github' => $failOpen,
            'google' => $failOpen,
            'allow_two_factor' => $failOpen,
            'require_two_factor' => false,
            'require_email_verification' => false,
            'email_login_code' => false,
            'email_login_magic_link' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return PolicySnapshot
     */
    private static function normalizePolicy(array $data): array
    {
        $failOpen = (bool) config('fleet_idp.socialite.policy_fail_open', true);

        $out = [
            'github' => self::coerceBool($data['github'] ?? null, $failOpen),
            'google' => self::coerceBool($data['google'] ?? null, $failOpen),
            'allow_two_factor' => self::coerceBool($data['allow_two_factor'] ?? null, true),
            'require_two_factor' => self::coerceBool($data['require_two_factor'] ?? null, false),
            'require_email_verification' => self::coerceBool($data['require_email_verification'] ?? null, false),
            'email_login_code' => self::coerceBool($data['email_login_code'] ?? null, false),
            'email_login_magic_link' => self::coerceBool($data['email_login_magic_link'] ?? null, false),
        ];

        if (! $out['allow_two_factor']) {
            $out['require_two_factor'] = false;
        }

        if ($out['require_two_factor']) {
            $out['allow_two_factor'] = true;
        }

        return $out;
    }

    private static function coerceBool(mixed $value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === null) {
            return $default;
        }

        if (is_int($value) || is_float($value)) {
            return ((int) $value) !== 0;
        }

        if (is_string($value)) {
            $v = strtolower(trim($value));
            if (in_array($v, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }
            if (in_array($v, ['0', 'false', 'no', 'off', ''], true)) {
                return false;
            }
        }

        return (bool) $value;
    }
}
