<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Fleet Auth (Passport) base URL
    |--------------------------------------------------------------------------
    |
    | Root URL of the Laravel Passport app only — not this app’s OAuth callback.
    |
    | Bootstrap (package 0.4+): on Fleet Auth set FLEET_AUTH_CLI_SETUP_TOKEN, then run
    | `php artisan fleet:idp:configure` in this app to create Passport clients and merge
    | secrets into .env. Composer: shaferllc/fleet-idp-client. See the package README.
    |
    */

    'url' => rtrim((string) env('FLEET_IDP_URL', ''), '/'),

    'client_id' => env('FLEET_IDP_CLIENT_ID', ''),

    'client_secret' => env('FLEET_IDP_CLIENT_SECRET', ''),

    /*
    | OAuth redirect URI sent to Fleet Auth. Must exactly match a value in the IdP
    | client's redirect_uris (see fleet-auth FLEET_IDP_REDIRECT_WAYPOST / _FLEET_CONSOLE).
    |
    | Leave FLEET_IDP_REDIRECT_URI unset to derive redirect_path from the current HTTP
    | request (scheme + host), or from APP_URL when there is no request (e.g. Artisan).
    | Set FLEET_IDP_REDIRECT_URI to one full URL only — never a comma-separated list
    | (register extra URIs on the Passport client in Fleet Auth).
    */

    'redirect_uri' => env('FLEET_IDP_REDIRECT_URI'),

    'redirect_path' => env('FLEET_IDP_REDIRECT_PATH', '/oauth/fleet-auth/callback'),

    /*
    | Password grant client (optional). Requires fleet_idp.user_model for sync.
    */

    'password_client_id' => env('FLEET_IDP_PASSWORD_CLIENT_ID', ''),

    'password_client_secret' => env('FLEET_IDP_PASSWORD_CLIENT_SECRET', ''),

    /*
    | Session key for OAuth CSRF state.
    */

    'session_oauth_state_key' => env('FLEET_IDP_SESSION_STATE_KEY', 'fleet_idp_oauth_state'),

    /*
    | Eloquent model for FleetIdpEloquentUserProvisioner / password grant (e.g. App\Models\User).
    */

    'user_model' => env('FLEET_IDP_USER_MODEL', 'App\\Models\\User'),

    /*
    | Value stored in users.provider when linking accounts.
    */

    'provider_name' => env('FLEET_IDP_PROVIDER_NAME', 'fleet_auth'),

    /*
    |--------------------------------------------------------------------------
    | Registration mirroring (optional)
    |--------------------------------------------------------------------------
    |
    | Hooks into {@see \Illuminate\Auth\Events\Registered}. Before creating the user,
    | call {@see \Fleet\IdpClient\Support\FleetProvisioningRequest::stashPasswordForRegisteredEvent}
    | with the plain password, then fire Registered as usual. The listener reads the
    | password from the current request (first match in password_request_keys) and
    | POSTs to Fleet Auth /api/provisioning/users. Leave url empty to use
    | {FLEET_IDP_URL}/api/provisioning/users.
    |
    */

    'provisioning' => [
        'token' => env('FLEET_AUTH_PROVISIONING_TOKEN', ''),
        'url' => env('FLEET_AUTH_PROVISIONING_URL', ''),
        'merge_request_key' => env('FLEET_IDP_PROVISIONING_REQUEST_KEY', '_fleet_idp_provisioning_password'),
        'password_request_keys' => array_values(array_filter(array_map('trim', explode(',', (string) env(
            'FLEET_IDP_PROVISIONING_PASSWORD_KEYS',
            '_fleet_idp_provisioning_password,password,form.password'
        ))))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Web OAuth (redirect + callback routes)
    |--------------------------------------------------------------------------
    |
    | Register GET routes that start the authorization-code flow and handle
    | the IdP callback. Set FLEET_IDP_WEB_ENABLED=false to register routes
    | manually in your app instead.
    |
    | mode: "eloquent" syncs /api/user into your user model and uses Auth::login.
    |       "session" stores IdP profile in session (Fleet Console style).
    |
    */

    'web' => [
        'enabled' => env('FLEET_IDP_WEB_ENABLED', true),

        'middleware' => array_values(array_filter(array_map('trim', explode(',', (string) env('FLEET_IDP_WEB_MIDDLEWARE', 'web'))))),

        'mode' => env('FLEET_IDP_WEB_MODE', 'eloquent'),

        'start_path' => env('FLEET_IDP_OAUTH_START_PATH', '/oauth/fleet-auth'),

        'failure_path' => env('FLEET_IDP_OAUTH_FAILURE_PATH', '/oauth/fleet-auth/failure'),

        'route_names' => [
            'redirect' => env('FLEET_IDP_ROUTE_OAUTH_REDIRECT', 'fleet-idp.oauth.redirect'),
            'callback' => env('FLEET_IDP_ROUTE_OAUTH_CALLBACK', 'fleet-idp.oauth.callback'),
            'failure' => env('FLEET_IDP_ROUTE_OAUTH_FAILURE', 'fleet-idp.oauth.failure'),
        ],

        'eloquent' => [
            'oauth_error_route' => env('FLEET_IDP_OAUTH_ERROR_ROUTE', 'fleet-idp.oauth.failure'),
            'try_again_route' => env('FLEET_IDP_TRY_AGAIN_ROUTE', 'login'),
            'oauth_error_session_key' => env('FLEET_IDP_OAUTH_ERROR_SESSION_KEY', 'oauth_error'),
            'post_login_route' => env('FLEET_IDP_POST_LOGIN_ROUTE', 'dashboard'),
            'two_factor_route' => env('FLEET_IDP_TWO_FACTOR_ROUTE', 'two-factor.challenge'),
        ],

        'session' => [
            'error_route' => env('FLEET_IDP_SESSION_OAUTH_ERROR_ROUTE', 'console.login'),
            'error_validation_key' => env('FLEET_IDP_SESSION_ERROR_KEY', 'password'),
            'auth_session_key' => env('FLEET_IDP_SESSION_AUTH_KEY', 'fleet_console_ok'),
            'user_session_key' => env('FLEET_IDP_SESSION_USER_KEY', 'fleet_idp_user'),
            'post_login_route' => env('FLEET_IDP_SESSION_POST_LOGIN_ROUTE', 'console.dashboard'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | GitHub / Google (Laravel Socialite) on this app
    |--------------------------------------------------------------------------
    |
    | Registers oauth/{provider} routes and optional Blade buttons. Client id/secret
    | stay in config/services.php (services.github, services.google). Fleet Auth
    | exposes GET /api/social-login/providers; this package caches that response
    | to hide buttons when the IdP disables a provider.
    |
    */

    'socialite' => [
        'enabled' => filter_var(env('FLEET_IDP_SOCIALITE_ENABLED', true), FILTER_VALIDATE_BOOL),

        'route_prefix' => env('FLEET_IDP_SOCIALITE_ROUTE_PREFIX', 'oauth'),

        'middleware' => array_values(array_filter(array_map('trim', explode(',', (string) env(
            'FLEET_IDP_SOCIALITE_MIDDLEWARE',
            'web'
        ))))),

        'providers_url' => env('FLEET_IDP_SOCIALITE_PROVIDERS_URL'),

        'policy_cache_seconds' => max(0, (int) env('FLEET_IDP_SOCIALITE_POLICY_CACHE', 60)),

        'policy_timeout_seconds' => max(1, (int) env('FLEET_IDP_SOCIALITE_POLICY_TIMEOUT', 3)),

        'policy_fail_open' => filter_var(env('FLEET_IDP_SOCIALITE_POLICY_FAIL_OPEN', true), FILTER_VALIDATE_BOOL),

        'null_password_for_social' => filter_var(env('FLEET_IDP_SOCIALITE_NULL_PASSWORD', true), FILTER_VALIDATE_BOOL),

        'user_model' => env('FLEET_IDP_SOCIALITE_USER_MODEL'),

        'error_route' => env('FLEET_IDP_SOCIALITE_ERROR_ROUTE', 'login'),

        'oauth_error_session_key' => env('FLEET_IDP_SOCIALITE_ERROR_KEY', 'oauth_error'),

        'post_login_route' => env('FLEET_IDP_SOCIALITE_POST_LOGIN_ROUTE', 'dashboard'),

        'two_factor_route' => env('FLEET_IDP_SOCIALITE_TWO_FACTOR_ROUTE', 'two-factor.challenge'),

        'two_factor_session_user_id_key' => env('FLEET_IDP_SOCIALITE_TWO_FACTOR_USER_KEY', 'two_factor.id'),

        'two_factor_session_remember_key' => env('FLEET_IDP_SOCIALITE_TWO_FACTOR_REMEMBER_KEY', 'two_factor.remember'),
    ],

];
