<?php

declare(strict_types=1);

namespace Fleet\IdpClient\Services;

use Fleet\IdpClient\FleetIdpOAuth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Reads Fleet Auth GET /api/social-login/providers so client apps only expose
 * GitHub/Google when the IdP allows it, and mirror Fleet database settings (2FA,
 * email login, Fleet OAuth visibility, satellite warm/fail-open hints).
 *
 * @phpstan-type PolicySnapshot array{
 *     github: bool,
 *     google: bool,
 *     fleet_login_enabled: bool,
 *     allow_two_factor: bool,
 *     require_two_factor: bool,
 *     require_email_verification: bool,
 *     email_login_code: bool,
 *     email_login_magic_link: bool,
 *     satellite_policy_fail_open: bool,
 *     satellite_warm_providers_each_request: bool,
 *     guest_email_login_card_without_idp_delivery: bool
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
     * Run after changing Integrations / IdP settings on Fleet Auth if satellites should pick them up immediately.
     */
    public static function forgetCachedSnapshot(): void
    {
        $ttl = max(0, (int) config('fleet_idp.socialite.policy_cache_seconds', 60));
        if ($ttl === 0) {
            return;
        }

        $base = rtrim((string) config('fleet_idp.url', ''), '/');
        if ($base === '') {
            return;
        }

        Cache::forget(self::policyCacheKey());
    }

    public static function policyCacheKey(): string
    {
        return 'fleet_idp.social_login_policy.'.md5(self::providersRequestUrl());
    }

    /**
     * Whether to resolve {@see snapshot()} at the start of this web request (warm cache / stay fresh).
     * Driven by Fleet Auth IdP settings (providers JSON), after the cache is first populated.
     */
    public static function shouldWarmPolicyOnThisRequest(): bool
    {
        if (app()->runningUnitTests()) {
            return false;
        }

        $base = rtrim((string) config('fleet_idp.url', ''), '/');
        if ($base === '') {
            return false;
        }

        $ttl = max(0, (int) config('fleet_idp.socialite.policy_cache_seconds', 60));
        if ($ttl === 0) {
            return true;
        }

        $cached = Cache::get(self::policyCacheKey());
        if (! is_array($cached)) {
            return true;
        }

        return (bool) ($cached['satellite_warm_providers_each_request'] ?? false);
    }

    public static function githubAllowed(): bool
    {
        return self::snapshot()['github'] ?? false;
    }

    public static function googleAllowed(): bool
    {
        return self::snapshot()['google'] ?? false;
    }

    public static function fleetLoginEnabled(): bool
    {
        return self::snapshot()['fleet_login_enabled'] ?? false;
    }

    public static function guestEmailLoginCardWithoutIdpDelivery(): bool
    {
        return self::snapshot()['guest_email_login_card_without_idp_delivery'] ?? false;
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

        $base = rtrim((string) config('fleet_idp.url', ''), '/');
        if ($base === '') {
            return self::localDevSnapshot();
        }

        $ttl = max(0, (int) config('fleet_idp.socialite.policy_cache_seconds', 60));
        $url = self::providersRequestUrl();

        if ($ttl === 0) {
            $fetched = self::fetchProviders($url);

            return self::normalizePolicy($fetched['ok'] ? $fetched['data'] : []);
        }

        $cacheKey = self::policyCacheKey();
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $fetched = self::fetchProviders($url);
        if (! $fetched['ok']) {
            // Do not cache failed / unreachable IdP responses — otherwise fleet_login_enabled
            // and other flags stay false until TTL expires.
            return self::normalizePolicy([]);
        }

        $normalized = self::normalizePolicy($fetched['data']);
        Cache::put($cacheKey, $normalized, $ttl);

        return $normalized;
    }

    /**
     * Full URL for GET /api/social-login/providers, including ?client_id= when configured.
     */
    public static function providersRequestUrl(): string
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
     * @return PolicySnapshot
     */
    private static function localDevSnapshot(): array
    {
        return self::normalizePolicy([]);
    }

    /**
     * @return array{ok: bool, data: array<string, mixed>}
     */
    private static function fetchProviders(string $url): array
    {
        try {
            $response = Http::timeout((int) config('fleet_idp.socialite.policy_timeout_seconds', 3))
                ->acceptJson()
                ->withOptions(FleetIdpOAuth::redirectPreservingPostOptions())
                ->get($url);
        } catch (\Throwable) {
            return ['ok' => false, 'data' => []];
        }

        if (! $response->successful()) {
            return ['ok' => false, 'data' => []];
        }

        /** @var array<string, mixed> $data */
        $data = $response->json();

        return ['ok' => true, 'data' => is_array($data) ? $data : []];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return PolicySnapshot
     */
    private static function normalizePolicy(array $data): array
    {
        $failOpen = self::coerceBool($data['satellite_policy_fail_open'] ?? null, false);

        $out = [
            'github' => self::coerceBool($data['github'] ?? null, $failOpen),
            'google' => self::coerceBool($data['google'] ?? null, $failOpen),
            'fleet_login_enabled' => self::coerceBool($data['fleet_login_enabled'] ?? null, false),
            'allow_two_factor' => self::coerceBool($data['allow_two_factor'] ?? null, true),
            'require_two_factor' => self::coerceBool($data['require_two_factor'] ?? null, false),
            'require_email_verification' => self::coerceBool($data['require_email_verification'] ?? null, false),
            'email_login_code' => self::coerceBool($data['email_login_code'] ?? null, false),
            'email_login_magic_link' => self::coerceBool($data['email_login_magic_link'] ?? null, false),
            'satellite_policy_fail_open' => $failOpen,
            'satellite_warm_providers_each_request' => self::coerceBool($data['satellite_warm_providers_each_request'] ?? null, false),
            'guest_email_login_card_without_idp_delivery' => self::coerceBool($data['guest_email_login_card_without_idp_delivery'] ?? null, false),
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
