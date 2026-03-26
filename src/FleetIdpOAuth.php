<?php

namespace Fleet\IdpClient;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class FleetIdpOAuth
{
    public static function isConfigured(): bool
    {
        $url = config('fleet_idp.url');
        $id = config('fleet_idp.client_id');
        $secret = config('fleet_idp.client_secret');

        if (! is_string($url) || $url === ''
            || ! is_string($id) || $id === ''
            || ! is_string($secret) || $secret === '') {
            return false;
        }

        try {
            self::requireIdpRootUrl();
        } catch (RuntimeException) {
            return false;
        }

        return true;
    }

    /**
     * Callback URL registered on the IdP client and sent on authorize + token exchange.
     */
    public static function redirectUri(): string
    {
        $configured = config('fleet_idp.redirect_uri');
        if (is_string($configured) && trim($configured) !== '') {
            $single = trim($configured);
            if (str_contains($single, ',')) {
                throw new InvalidRedirectUriConfig(
                    'FLEET_IDP_REDIRECT_URI must be a single URL, not a comma-separated list. '.
                    'Register each callback on the Passport client in Fleet Auth only. '.
                    'Use one URL that matches how you open this app (e.g. http://waypost.test/oauth/fleet-auth/callback), or remove FLEET_IDP_REDIRECT_URI so the package derives it from the browser request.'
                );
            }

            return rtrim($single, '/');
        }

        $path = (string) config('fleet_idp.redirect_path', '/oauth/fleet-auth/callback');
        $path = '/'.ltrim(trim($path), '/');

        return rtrim(self::implicitOAuthApplicationRoot(), '/').$path;
    }

    /**
     * Public base URL for this app (scheme + host), from the current request when available.
     */
    public static function applicationHttpRoot(): string
    {
        return self::implicitOAuthApplicationRoot();
    }

    /**
     * Root URL for this app when redirect_uri is not set explicitly (no FLEET_IDP_REDIRECT_URI).
     * Uses the incoming request so https://waypost.test matches Passport even if APP_URL is localhost.
     */
    protected static function implicitOAuthApplicationRoot(): string
    {
        if (app()->bound('request')) {
            $request = request();
            if ($request !== null && $request->getHost() !== '') {
                return $request->getSchemeAndHttpHost();
            }
        }

        return rtrim((string) config('app.url'), '/');
    }

    /**
     * Validated Fleet Auth base URL (scheme + host, optional path prefix), never a trailing /oauth/… endpoint.
     */
    public static function requireIdpRootUrl(): string
    {
        $raw = config('fleet_idp.url');
        $base = rtrim((string) $raw, '/');
        if ($base === '') {
            throw new RuntimeException('FLEET_IDP_URL is empty.');
        }

        if (! filter_var($base, FILTER_VALIDATE_URL) || ! Str::startsWith(Str::lower($base), ['http://', 'https://'])) {
            throw new RuntimeException(
                'FLEET_IDP_URL must be a full URL to the Fleet Auth app (e.g. https://fleet-auth.test), not a relative path. Current value: '.json_encode($raw)
            );
        }

        $path = parse_url($base, PHP_URL_PATH);
        $path = is_string($path) ? rtrim($path, '/') : '';
        if (str_ends_with($path, '/oauth/fleet-auth/callback')
            || str_ends_with($path, '/oauth/authorize')
            || str_ends_with($path, '/auth/callback')) {
            throw new RuntimeException(
                'FLEET_IDP_URL must be only the Fleet Auth server root (e.g. https://fleet-auth.test). You pasted this app\'s OAuth callback URL — put that in APP_URL and FLEET_IDP_REDIRECT_URI instead.'
            );
        }

        $appBase = rtrim((string) config('app.url'), '/');
        if ($appBase !== '' && $base === $appBase) {
            throw new RuntimeException(
                'FLEET_IDP_URL must not equal APP_URL. Point FLEET_IDP_URL at the Fleet Auth (Passport) server, not this application.'
            );
        }

        return $base;
    }

    public static function authorizationRedirectUrl(): string
    {
        if (! self::isConfigured()) {
            throw new RuntimeException('Fleet IdP OAuth is not configured.');
        }

        $state = Str::random(40);
        $stateKey = (string) config('fleet_idp.session_oauth_state_key');
        session([$stateKey => $state]);

        $query = http_build_query([
            'client_id' => config('fleet_idp.client_id'),
            'redirect_uri' => self::redirectUri(),
            'response_type' => 'code',
            'scope' => '',
            'state' => $state,
        ]);

        return self::requireIdpRootUrl().'/oauth/authorize?'.$query;
    }

    /**
     * @return array{access_token: string, token_type: string, expires_in?: int}
     */
    public static function exchangeCode(string $code): array
    {
        if (! self::isConfigured()) {
            throw new RuntimeException('Fleet IdP OAuth is not configured.');
        }

        /** @var Response $response */
        $response = Http::asForm()
            ->acceptJson()
            ->withOptions(self::redirectPreservingPostOptions())
            ->post(self::requireIdpRootUrl().'/oauth/token', [
                'grant_type' => 'authorization_code',
                'client_id' => config('fleet_idp.client_id'),
                'client_secret' => config('fleet_idp.client_secret'),
                'redirect_uri' => self::redirectUri(),
                'code' => $code,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(self::fleetAuthHttpErrorSummary($response, 'Fleet Auth token'));
        }

        /** @var array{access_token: string, token_type: string, expires_in?: int} $data */
        $data = $response->json();

        return $data;
    }

    /**
     * @return array{id: int|string, name: string, email: string}
     */
    public static function fetchUser(string $accessToken): array
    {
        if (! self::isConfigured()) {
            throw new RuntimeException('Fleet IdP OAuth is not configured.');
        }

        return self::fetchUserWithToken($accessToken);
    }

    /**
     * Load the IdP user profile using only FLEET_IDP_URL (for password grant after token issue).
     *
     * @return array{id: int|string, name?: string|null, email?: string|null}
     */
    public static function fetchUserWithToken(string $accessToken): array
    {
        $base = self::requireIdpRootUrl();

        /** @var Response $response */
        $response = self::getJsonWithBearerFollowingRedirects($base.'/api/user', $accessToken);

        if (! $response->successful()) {
            throw new RuntimeException(self::fleetAuthHttpErrorSummary($response, 'Fleet Auth profile'));
        }

        /** @var array{id: int|string, name?: string|null, email?: string|null} $data */
        $data = $response->json();

        return $data;
    }

    /**
     * Guzzle follows 302/301 by reissuing as GET, which breaks POST /oauth/token after e.g. http→https redirects.
     *
     * @return array<string, mixed>
     */
    public static function redirectPreservingPostOptions(): array
    {
        return [
            'allow_redirects' => [
                'max' => 5,
                'strict' => true,
                'protocols' => ['http', 'https'],
            ],
        ];
    }

    /**
     * GET JSON with Bearer auth, following redirects without Guzzle's automatic redirect handler.
     *
     * Guzzle removes Authorization on "cross-origin" redirects; http→https on the same host counts,
     * which yields 401 Unauthenticated on Passport's GET /api/user.
     *
     * @see \GuzzleHttp\RedirectMiddleware
     */
    protected static function getJsonWithBearerFollowingRedirects(string $url, string $bearerToken): Response
    {
        $current = $url;
        $max = 5;
        $response = null;

        for ($i = 0; $i < $max; $i++) {
            /** @var Response $response */
            $response = Http::withToken($bearerToken)
                ->acceptJson()
                ->withOptions(['allow_redirects' => false])
                ->get($current);

            if ($response->successful()) {
                return $response;
            }

            $status = $response->status();
            if ($status >= 300 && $status < 400) {
                $location = $response->header('Location');
                if (! is_string($location) || trim($location) === '') {
                    return $response;
                }

                $current = (string) UriResolver::resolve(new Uri($current), new Uri(trim($location)));

                continue;
            }

            return $response;
        }

        throw new RuntimeException('Fleet Auth profile: too many HTTP redirects when calling /api/user.');
    }

    /**
     * Human-readable line for logs and the OAuth failure page (OAuth JSON errors are safe to show).
     */
    protected static function fleetAuthHttpErrorSummary(Response $response, string $label): string
    {
        $json = $response->json();
        if (is_array($json)) {
            $desc = trim((string) ($json['error_description'] ?? ''));
            if ($desc !== '') {
                return $label.': '.$desc;
            }
            $err = trim((string) ($json['error'] ?? ''));
            if ($err !== '') {
                return $label.': '.$err;
            }
            $msg = trim((string) ($json['message'] ?? ''));
            if ($msg !== '') {
                return $label.': '.$msg;
            }
        }

        return $label.' (HTTP '.$response->status().').';
    }
}
