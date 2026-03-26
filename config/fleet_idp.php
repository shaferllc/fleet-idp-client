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

];
