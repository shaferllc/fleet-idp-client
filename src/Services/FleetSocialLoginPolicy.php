<?php

declare(strict_types=1);

namespace Fleet\IdpClient\Services;

use Fleet\IdpClient\FleetIdpOAuth;
use Fleet\IdpClient\FleetIdpPasswordGrant;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
 *     guest_email_login_card_without_idp_delivery: bool,
 *     satellite_policy_debug: bool
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
        // Bump version when snapshot semantics change so stale “all false” entries are dropped.
        return 'fleet_idp.social_login_policy.v3.'.md5(self::providersRequestUrl());
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
            self::policyDebugLog('using FleetSocialLoginPolicy::fake() override (no HTTP)', [
                'override_keys' => array_keys(self::$runtimeOverride),
            ]);

            return self::normalizePolicy(self::$runtimeOverride);
        }

        $base = rtrim((string) config('fleet_idp.url', ''), '/');
        if ($base === '') {
            self::policyDebugLog('FLEET_IDP_URL empty; using empty policy defaults (no IdP request)', [
                'oauth_env_looks_configured' => FleetIdpOAuth::isConfigured(),
            ]);

            return self::localDevSnapshot();
        }

        $ttl = max(0, (int) config('fleet_idp.socialite.policy_cache_seconds', 60));
        $url = self::providersRequestUrl();

        if ($ttl === 0) {
            self::policyDebugLog('policy_cache_seconds=0; live GET (no cache)', [
                'url' => $url,
                'verify_tls' => (bool) filter_var(config('fleet_idp.provisioning.verify_ssl', true), FILTER_VALIDATE_BOOL),
            ]);
            $fetched = self::fetchProviders($url);

            if ($fetched['ok']) {
                $normalized = self::normalizePolicy($fetched['data']);
                self::policyDebugLog('live GET succeeded', ['flags' => self::policyFlagsForLog($normalized)]);

                return $normalized;
            }

            self::policyDebugLog('live GET failed; using failed-fetch / optimistic path', []);

            return self::snapshotFromFailedFetch();
        }

        $cacheKey = self::policyCacheKey();
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            self::policyDebugLog('cache hit', [
                'cache_key_tail' => substr($cacheKey, -12),
                'flags' => self::policyFlagsForLog($cached),
            ]);

            return $cached;
        }

        self::policyDebugLog('cache miss; GET providers', [
            'url' => $url,
            'verify_tls' => (bool) filter_var(config('fleet_idp.provisioning.verify_ssl', true), FILTER_VALIDATE_BOOL),
            'ttl_seconds' => $ttl,
        ]);

        $fetched = self::fetchProviders($url);
        if (! $fetched['ok']) {
            // Do not cache failed / unreachable IdP responses — otherwise fleet_login_enabled
            // and other flags stay false until TTL expires.
            self::policyDebugLog('GET failed; not caching; using failed-fetch / optimistic path', []);

            return self::snapshotFromFailedFetch();
        }

        $normalized = self::normalizePolicy($fetched['data']);
        Cache::put($cacheKey, $normalized, $ttl);
        self::policyDebugLog('GET succeeded; cached snapshot', [
            'cache_key_tail' => substr($cacheKey, -12),
            'flags' => self::policyFlagsForLog($normalized),
        ]);

        return $normalized;
    }

    /**
     * Whether the Fleet IdP on-page debug panel should render. True when
     * {@see config('fleet_idp.socialite.debug_policy_fetch')} is set (FLEET_IDP_DEBUG_SOCIAL_POLICY) or when
     * Fleet Auth returns {@code satellite_policy_debug} for this app's {@code client_id} in the providers JSON.
     * Verbose policy logs ({@see policyDebugLog}) still require the env flag only.
     */
    public static function isDebugPanelEnabled(): bool
    {
        if (filter_var(config('fleet_idp.socialite.debug_policy_fetch', false), FILTER_VALIDATE_BOOL)) {
            return true;
        }

        return (bool) (self::snapshot()['satellite_policy_debug'] ?? false);
    }

    /**
     * One-shot diagnostics for Fleet Auth GET /api/social-login/providers (config, HTTP attempts,
     * and the policy snapshot the app resolves). Use from
     * {@see \Fleet\IdpClient\Console\DebugSocialLoginPolicyCommand} or tinker. Per-request logs use
     * config fleet_idp.socialite.debug_policy_fetch (FLEET_IDP_DEBUG_SOCIAL_POLICY).
     *
     * @return array<string, mixed>
     */
    public static function diagnostics(): array
    {
        $base = rtrim((string) config('fleet_idp.url', ''), '/');
        $snapshot = self::snapshot();
        $resolved = self::policyFlagsForLog($snapshot);

        return [
            'app_env' => app()->environment(),
            'fleet_idp_base_url' => $base === '' ? null : $base,
            'client_id_set' => filled(config('fleet_idp.client_id')),
            'client_secret_set' => filled(config('fleet_idp.client_secret')),
            'oauth_configured' => FleetIdpOAuth::isConfigured(),
            'password_grant_configured' => FleetIdpPasswordGrant::isConfigured(),
            'verify_tls' => (bool) filter_var(config('fleet_idp.provisioning.verify_ssl', true), FILTER_VALIDATE_BOOL),
            'optimistic_when_unreachable' => (bool) filter_var(config('fleet_idp.socialite.optimistic_when_unreachable', false), FILTER_VALIDATE_BOOL),
            'policy_cache_seconds' => (int) config('fleet_idp.socialite.policy_cache_seconds', 60),
            'policy_timeout_seconds' => (int) config('fleet_idp.socialite.policy_timeout_seconds', 3),
            'debug_policy_fetch_enabled' => (bool) filter_var(config('fleet_idp.socialite.debug_policy_fetch', false), FILTER_VALIDATE_BOOL),
            'satellite_policy_debug_from_snapshot' => (bool) ($snapshot['satellite_policy_debug'] ?? false),
            'debug_panel_effective' => self::isDebugPanelEnabled(),
            'using_fake_override' => self::$runtimeOverride !== null,
            'providers_request_url' => $base === '' ? null : self::providersRequestUrl(),
            'policy_cache_key' => $base === '' ? null : self::policyCacheKey(),
            'resolved_snapshot' => $resolved,
            'http_probe_attempts' => $base === '' ? [] : self::probeProvidersHttp(),
        ];
    }

    /**
     * Lightweight payload for on-page debug UI (no extra HTTP — uses current {@see snapshot()} only).
     * Shown when {@see isDebugPanelEnabled()} is true.
     *
     * @return array<string, mixed>
     */
    public static function debugPanelPayload(): array
    {
        $base = rtrim((string) config('fleet_idp.url', ''), '/');
        $clientId = (string) config('fleet_idp.client_id', '');
        $clientIdPreview = $clientId === ''
            ? null
            : (strlen($clientId) > 14 ? substr($clientId, 0, 8).'…'.substr($clientId, -4) : $clientId);
        $snapshot = self::snapshot();

        return [
            'app_env' => app()->environment(),
            'fleet_idp_base_url' => $base === '' ? null : $base,
            'oauth_client_id_preview' => $clientIdPreview,
            'oauth_client_secret_configured' => filled(config('fleet_idp.client_secret')),
            'oauth_fully_configured' => FleetIdpOAuth::isConfigured(),
            'password_grant_configured' => FleetIdpPasswordGrant::isConfigured(),
            'verify_tls' => (bool) filter_var(config('fleet_idp.provisioning.verify_ssl', true), FILTER_VALIDATE_BOOL),
            'optimistic_unreachable_env' => (bool) filter_var(config('fleet_idp.socialite.optimistic_when_unreachable', false), FILTER_VALIDATE_BOOL),
            'policy_cache_seconds' => (int) config('fleet_idp.socialite.policy_cache_seconds', 60),
            'always_show_guest_email_card' => (bool) filter_var(config('fleet_idp.email_sign_in.always_show_guest_card_on_login', false), FILTER_VALIDATE_BOOL),
            'debug_env_override' => (bool) filter_var(config('fleet_idp.socialite.debug_policy_fetch', false), FILTER_VALIDATE_BOOL),
            'debug_from_idp_policy' => (bool) ($snapshot['satellite_policy_debug'] ?? false),
            'using_policy_fake' => self::$runtimeOverride !== null,
            'providers_request_url' => $base === '' ? null : self::providersRequestUrl(),
            'policy_cache_key_tail' => $base === '' ? null : substr(self::policyCacheKey(), -20),
            'resolved_policy_flags' => self::policyFlagsForLog($snapshot),
        ];
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
     * When the providers endpoint cannot be reached, return safe defaults unless the satellite
     * is clearly wired to Fleet OAuth — then optionally assume IdP features are enabled so the
     * login UI is not blank (browser OAuth / email flows may still work).
     *
     * @return PolicySnapshot
     */
    private static function snapshotFromFailedFetch(): array
    {
        $normalized = self::normalizePolicy([]);
        $merged = self::mergeOptimisticUnreachableDefaults($normalized);
        self::policyDebugLog('failed-fetch snapshot', [
            'before_optimistic' => self::policyFlagsForLog($normalized),
            'after_optimistic' => self::policyFlagsForLog($merged),
        ]);

        return $merged;
    }

    /**
     * @param  PolicySnapshot  $normalized
     * @return PolicySnapshot
     */
    private static function mergeOptimisticUnreachableDefaults(array $normalized): array
    {
        if (! FleetIdpOAuth::isConfigured()) {
            self::policyDebugLog('optimistic defaults skipped (Fleet OAuth env incomplete)', []);

            return $normalized;
        }

        $optimistic = app()->environment('local')
            || filter_var(config('fleet_idp.socialite.optimistic_when_unreachable', false), FILTER_VALIDATE_BOOL);

        if (! $optimistic) {
            self::policyDebugLog('optimistic defaults skipped (not local and FLEET_IDP_OPTIMISTIC_UNREACHABLE=false)', [
                'app_env' => app()->environment(),
            ]);

            return $normalized;
        }

        self::policyDebugLog('applying optimistic defaults for fleet_login + email sign-in flags', [
            'app_env' => app()->environment(),
            'optimistic_when_unreachable_env' => (bool) filter_var(config('fleet_idp.socialite.optimistic_when_unreachable', false), FILTER_VALIDATE_BOOL),
        ]);

        $normalized['fleet_login_enabled'] = true;
        $normalized['email_login_magic_link'] = true;
        $normalized['email_login_code'] = true;

        return $normalized;
    }

    /**
     * TLS verify follows {@see config('fleet_idp.provisioning.verify_ssl')} so local HTTPS
     * (e.g. fleet-auth.test) matches provisioning and other IdP HTTP calls.
     *
     * @return array<string, mixed>
     */
    private static function policyHttpClientOptions(): array
    {
        $options = FleetIdpOAuth::redirectPreservingPostOptions();
        if (! filter_var(config('fleet_idp.provisioning.verify_ssl', true), FILTER_VALIDATE_BOOL)) {
            $options['verify'] = false;
        }

        return $options;
    }

    /**
     * Base GET /api/social-login/providers URL with no client_id query (Fleet site defaults).
     */
    private static function providersUrlWithoutClientQuery(): string
    {
        $explicit = config('fleet_idp.socialite.providers_url');
        if (is_string($explicit) && trim($explicit) !== '') {
            return self::httpUrlWithoutQueryParam(trim($explicit), 'client_id');
        }

        $base = rtrim((string) config('fleet_idp.url', ''), '/');

        return $base.'/api/social-login/providers';
    }

    private static function httpUrlWithoutQueryParam(string $url, string $param): string
    {
        $parts = parse_url($url);
        if (! is_array($parts)) {
            return $url;
        }

        $query = [];
        if (isset($parts['query']) && $parts['query'] !== '') {
            parse_str($parts['query'], $query);
        }
        unset($query[$param]);
        $scheme = isset($parts['scheme']) ? $parts['scheme'].'://' : '';
        $user = $parts['user'] ?? '';
        $pass = isset($parts['pass']) ? ':'.$parts['pass'] : '';
        $auth = $user !== '' ? $user.$pass.'@' : '';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = $parts['path'] ?? '';
        $qs = http_build_query($query);

        return $scheme.$auth.$host.$port.$path.($qs !== '' ? '?'.$qs : '');
    }

    /**
     * @return array{ok: bool, data: array<string, mixed>}
     */
    private static function fetchProviders(string $url): array
    {
        $options = self::policyHttpClientOptions();
        $timeout = (int) config('fleet_idp.socialite.policy_timeout_seconds', 3);

        self::policyDebugLog('HTTP GET', [
            'url' => $url,
            'timeout_seconds' => $timeout,
            'verify_tls' => ($options['verify'] ?? true) !== false,
        ]);

        try {
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->withOptions($options)
                ->get($url);
        } catch (\Throwable $e) {
            self::policyDebugLog('HTTP GET exception', [
                'url' => $url,
                'exception' => $e->getMessage(),
            ]);

            return ['ok' => false, 'data' => []];
        }

        if ($response->successful()) {
            /** @var array<string, mixed> $data */
            $data = $response->json();
            $data = is_array($data) ? $data : [];
            self::policyDebugLog('HTTP GET success', [
                'url' => $url,
                'status' => $response->status(),
                'json_keys' => array_keys($data),
            ]);

            return ['ok' => true, 'data' => $data];
        }

        $status = $response->status();
        self::policyDebugLog('HTTP GET non-success', [
            'url' => $url,
            'status' => $status,
        ]);

        if (in_array($status, [404, 422], true) && str_contains($url, 'client_id=')) {
            $fallback = self::providersUrlWithoutClientQuery();
            if ($fallback !== $url) {
                self::policyDebugLog('retrying GET without client_id (site defaults)', [
                    'fallback_url' => $fallback,
                ]);

                try {
                    $retry = Http::timeout($timeout)
                        ->acceptJson()
                        ->withOptions($options)
                        ->get($fallback);
                } catch (\Throwable $e) {
                    self::policyDebugLog('fallback GET exception', [
                        'url' => $fallback,
                        'exception' => $e->getMessage(),
                    ]);

                    return ['ok' => false, 'data' => []];
                }

                if ($retry->successful()) {
                    /** @var array<string, mixed> $data */
                    $data = $retry->json();
                    $data = is_array($data) ? $data : [];
                    self::policyDebugLog('fallback GET success', [
                        'url' => $fallback,
                        'status' => $retry->status(),
                        'json_keys' => array_keys($data),
                    ]);

                    return ['ok' => true, 'data' => $data];
                }

                self::policyDebugLog('fallback GET failed', [
                    'url' => $fallback,
                    'status' => $retry->status(),
                ]);
            }
        }

        return ['ok' => false, 'data' => []];
    }

    private static function policyDebugEnabled(): bool
    {
        if (self::$runtimeOverride !== null) {
            return false;
        }

        if (app()->runningUnitTests()) {
            return false;
        }

        return filter_var(config('fleet_idp.socialite.debug_policy_fetch', false), FILTER_VALIDATE_BOOL);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function policyDebugLog(string $message, array $context = []): void
    {
        if (! self::policyDebugEnabled()) {
            return;
        }

        Log::debug('[fleet_idp] social-login policy: '.$message, $context);
    }

    /**
     * @param  PolicySnapshot  $snapshot
     * @return array<string, bool>
     */
    private static function policyFlagsForLog(array $snapshot): array
    {
        $keys = [
            'fleet_login_enabled',
            'email_login_code',
            'email_login_magic_link',
            'github',
            'google',
            'guest_email_login_card_without_idp_delivery',
            'satellite_policy_fail_open',
            'allow_two_factor',
            'require_two_factor',
            'require_email_verification',
        ];
        $out = [];
        foreach ($keys as $k) {
            $out[$k] = (bool) ($snapshot[$k] ?? false);
        }

        return $out;
    }

    /**
     * Fresh GET probe(s) for debugging (does not write cache). Mirrors {@see fetchProviders} paths.
     *
     * @return list<array<string, mixed>>
     */
    private static function probeProvidersHttp(): array
    {
        $attempts = [];
        $options = self::policyHttpClientOptions();
        $timeout = (int) config('fleet_idp.socialite.policy_timeout_seconds', 3);
        $url = self::providersRequestUrl();

        $record = function (string $u, Response $response, ?string $exception = null) use (&$attempts): void {
            $json = $response->json();
            $attempts[] = [
                'url' => $u,
                'status' => $response->status(),
                'ok' => $response->successful(),
                'json_top_level_keys' => is_array($json) ? array_keys($json) : null,
                'json_message' => is_array($json) ? ($json['message'] ?? null) : null,
                'exception' => $exception,
            ];
        };

        try {
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->withOptions($options)
                ->get($url);
        } catch (\Throwable $e) {
            $attempts[] = [
                'url' => $url,
                'status' => null,
                'ok' => false,
                'exception' => $e->getMessage(),
            ];

            return $attempts;
        }

        $record($url, $response, null);

        if ($response->successful()) {
            return $attempts;
        }

        $status = $response->status();
        if (in_array($status, [404, 422], true) && str_contains($url, 'client_id=')) {
            $fallback = self::providersUrlWithoutClientQuery();
            if ($fallback !== $url) {
                try {
                    $retry = Http::timeout($timeout)
                        ->acceptJson()
                        ->withOptions($options)
                        ->get($fallback);
                } catch (\Throwable $e) {
                    $attempts[] = [
                        'url' => $fallback,
                        'status' => null,
                        'ok' => false,
                        'exception' => $e->getMessage(),
                    ];

                    return $attempts;
                }

                $record($fallback, $retry, null);
            }
        }

        return $attempts;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return PolicySnapshot
     */
    private static function normalizePolicy(array $data): array
    {
        $failOpen = self::coerceBool($data['satellite_policy_fail_open'] ?? null, false);

        $fleetLoginDefault = self::socialProvidersPayloadLooksLikeIdp($data);
        $fleetLoginEnabled = array_key_exists('fleet_login_enabled', $data)
            ? self::coerceBool($data['fleet_login_enabled'], false)
            : $fleetLoginDefault;

        $out = [
            'github' => self::coerceBool($data['github'] ?? null, $failOpen),
            'google' => self::coerceBool($data['google'] ?? null, $failOpen),
            'fleet_login_enabled' => $fleetLoginEnabled,
            'allow_two_factor' => self::coerceBool($data['allow_two_factor'] ?? null, true),
            'require_two_factor' => self::coerceBool($data['require_two_factor'] ?? null, false),
            'require_email_verification' => self::coerceBool($data['require_email_verification'] ?? null, false),
            'email_login_code' => self::coerceBool($data['email_login_code'] ?? null, false),
            'email_login_magic_link' => self::coerceBool($data['email_login_magic_link'] ?? null, false),
            'satellite_policy_fail_open' => $failOpen,
            'satellite_warm_providers_each_request' => self::coerceBool($data['satellite_warm_providers_each_request'] ?? null, false),
            'guest_email_login_card_without_idp_delivery' => self::coerceBool($data['guest_email_login_card_without_idp_delivery'] ?? null, false),
            'satellite_policy_debug' => self::coerceBool($data['satellite_policy_debug'] ?? null, false),
        ];

        if (! $out['allow_two_factor']) {
            $out['require_two_factor'] = false;
        }

        if ($out['require_two_factor']) {
            $out['allow_two_factor'] = true;
        }

        return $out;
    }

    /**
     * True when JSON looks like Fleet Auth GET /api/social-login/providers (merged hints + flags),
     * not an empty transport failure or a partial test fake. If the payload omits fleet_login_enabled
     * but is clearly from the IdP, default Fleet OAuth on so “Allow Login with Fleet” is not hidden
     * due to a missing JSON key on older IdPs or proxies.
     */
    private static function socialProvidersPayloadLooksLikeIdp(array $data): bool
    {
        if ($data === []) {
            return false;
        }

        return array_key_exists('fleet_idp_url', $data)
            || array_key_exists('allow_registration', $data)
            || array_key_exists('satellite_policy_fail_open', $data);
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
