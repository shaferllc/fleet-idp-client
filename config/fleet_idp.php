<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Fleet Auth (Passport) base URL
    |--------------------------------------------------------------------------
    |
    | Root URL of the Laravel Passport app only — not this app’s OAuth callback.
    |
    */

    'url' => rtrim((string) env('FLEET_IDP_URL', ''), '/'),

    'client_id' => env('FLEET_IDP_CLIENT_ID', ''),

    'client_secret' => env('FLEET_IDP_CLIENT_SECRET', ''),

    /*
    | OAuth redirect URI sent to Fleet Auth. Must exactly match a value in the IdP
    | client's redirect_uris (see fleet-auth FLEET_IDP_REDIRECT_WAYPOST / _FLEET_CONSOLE).
    |
    | Leave FLEET_IDP_REDIRECT_URI unset to derive: rtrim(APP_URL) + redirect_path.
    | Fleet Console: set FLEET_IDP_REDIRECT_PATH=/auth/callback (or set full FLEET_IDP_REDIRECT_URI).
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

        'route_names' => [
            'redirect' => env('FLEET_IDP_ROUTE_OAUTH_REDIRECT', 'fleet-idp.oauth.redirect'),
            'callback' => env('FLEET_IDP_ROUTE_OAUTH_CALLBACK', 'fleet-idp.oauth.callback'),
        ],

        'eloquent' => [
            'oauth_error_route' => env('FLEET_IDP_OAUTH_ERROR_ROUTE', 'login'),
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

];
