<?php

declare(strict_types=1);

namespace Fleet\IdpClient\Services;

use Fleet\IdpClient\FleetIdpOAuth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Reads Fleet Auth GET /api/social-login/providers so client apps only expose
 * GitHub/Google when the IdP allows it.
 */
final class FleetSocialLoginPolicy
{
    /** @var array{github: bool, google: bool}|null */
    private static ?array $runtimeOverride = null;

    /**
     * @param  array{github: bool, google: bool}|null  $policy
     */
    public static function fake(?array $policy): void
    {
        self::$runtimeOverride = $policy;
    }

    public static function clearFake(): void
    {
        self::$runtimeOverride = null;
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
     * @return array{github: bool, google: bool}
     */
    public static function snapshot(): array
    {
        if (self::$runtimeOverride !== null) {
            return self::$runtimeOverride;
        }

        if (! self::packageEnabled()) {
            return ['github' => false, 'google' => false];
        }

        $base = rtrim((string) config('fleet_idp.url', ''), '/');
        if ($base === '') {
            return ['github' => true, 'google' => true];
        }

        $ttl = max(0, (int) config('fleet_idp.socialite.policy_cache_seconds', 60));
        $url = self::providersRequestUrl();

        if ($ttl === 0) {
            return self::fetchOrFallback($url);
        }

        $cacheKey = 'fleet_idp.social_login_policy.'.md5($url);

        return Cache::remember($cacheKey, $ttl, static fn (): array => self::fetchOrFallback($url));
    }

    private static function packageEnabled(): bool
    {
        return (bool) config('fleet_idp.socialite.enabled', true);
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
     * @return array{github: bool, google: bool}
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
            return $failOpen ? ['github' => true, 'google' => true] : ['github' => false, 'google' => false];
        }

        if (! $response->successful()) {
            return $failOpen ? ['github' => true, 'google' => true] : ['github' => false, 'google' => false];
        }

        /** @var array<string, mixed> $data */
        $data = $response->json();

        return [
            'github' => (bool) ($data['github'] ?? $failOpen),
            'google' => (bool) ($data['google'] ?? $failOpen),
        ];
    }
}
