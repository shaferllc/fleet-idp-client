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

    /*
     * Passport authorization client UUID from Fleet Auth. Required for accurate policy from
     * GET /api/social-login/providers?client_id= (2FA allow/require, email sign-in, etc.).
     */
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
    | {FLEET_IDP_URL}/api/provisioning/users. Forgot-password uses
    | POST .../lookup and POST .../password-reset (same Bearer) so users stay on the satellite UI.
    |
    */

    'provisioning' => [
        'token' => env('FLEET_AUTH_PROVISIONING_TOKEN', ''),
        'url' => env('FLEET_AUTH_PROVISIONING_URL', ''),
        /*
        | Optional full URL for POST email lookup (default: {url}/lookup or
        | {FLEET_IDP_URL}/api/provisioning/users/lookup). Same Bearer as provisioning.
        */
        'lookup_url' => env('FLEET_AUTH_PROVISIONING_LOOKUP_URL', ''),
        /*
        | Optional full URL for POST password-reset request (default: {url}/password-reset or
        | {FLEET_IDP_URL}/api/provisioning/users/password-reset). Same Bearer as provisioning.
        */
        'password_reset_url' => env('FLEET_AUTH_PROVISIONING_PASSWORD_RESET_URL', ''),
        /*
        | Optional full URL for POST password-change (default: {url}/password-change or
        | {FLEET_IDP_URL}/api/provisioning/users/password-change). Same Bearer as provisioning.
        */
        'password_change_url' => env('FLEET_AUTH_PROVISIONING_PASSWORD_CHANGE_URL', ''),
        /*
        | Guzzle TLS verify for provisioning HTTP calls. Set false only for local dev if
        | Fleet Auth uses HTTPS with a certificate PHP does not trust.
        */
        'verify_ssl' => filter_var(env('FLEET_IDP_PROVISIONING_VERIFY_SSL', true), FILTER_VALIDATE_BOOL),
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
    | to hide buttons when the IdP disables a provider. When enabled is false,
    | Socialite routes are omitted and social-login-buttons hides GitHub, Google,
    | and the Fleet OAuth button (password grant is unaffected).
    |
    */

    'socialite' => [
        'enabled' => filter_var(env('FLEET_IDP_SOCIALITE_ENABLED', false), FILTER_VALIDATE_BOOL),

        'route_prefix' => env('FLEET_IDP_SOCIALITE_ROUTE_PREFIX', 'oauth'),

        'middleware' => array_values(array_filter(array_map('trim', explode(',', (string) env(
            'FLEET_IDP_SOCIALITE_MIDDLEWARE',
            'web'
        ))))),

        'providers_url' => env('FLEET_IDP_SOCIALITE_PROVIDERS_URL'),

        /*
         * 0 = always refetch. After changing Fleet Auth Integrations, run
         * php artisan fleet:idp:forget-social-login-policy-cache on satellites (or cache:clear).
         */
        'policy_cache_seconds' => max(0, (int) env('FLEET_IDP_SOCIALITE_POLICY_CACHE', 60)),

        /*
         * Prepend middleware to the web group so each HTTP request resolves the providers
         * policy early (Octane-safe; disable with FLEET_IDP_SOCIALITE_WARM_POLICY_MIDDLEWARE=false).
         */
        'warm_policy_middleware' => filter_var(env('FLEET_IDP_SOCIALITE_WARM_POLICY_MIDDLEWARE', false), FILTER_VALIDATE_BOOL),

        'policy_timeout_seconds' => max(1, (int) env('FLEET_IDP_SOCIALITE_POLICY_TIMEOUT', 3)),

        'policy_fail_open' => filter_var(env('FLEET_IDP_SOCIALITE_POLICY_FAIL_OPEN', false), FILTER_VALIDATE_BOOL),

        'null_password_for_social' => filter_var(env('FLEET_IDP_SOCIALITE_NULL_PASSWORD', true), FILTER_VALIDATE_BOOL),

        'user_model' => env('FLEET_IDP_SOCIALITE_USER_MODEL'),

        'error_route' => env('FLEET_IDP_SOCIALITE_ERROR_ROUTE', 'login'),

        'oauth_error_session_key' => env('FLEET_IDP_SOCIALITE_ERROR_KEY', 'oauth_error'),

        'post_login_route' => env('FLEET_IDP_SOCIALITE_POST_LOGIN_ROUTE', 'dashboard'),

        'two_factor_route' => env('FLEET_IDP_SOCIALITE_TWO_FACTOR_ROUTE', 'two-factor.challenge'),

        'two_factor_session_user_id_key' => env('FLEET_IDP_SOCIALITE_TWO_FACTOR_USER_KEY', 'two_factor.id'),

        'two_factor_session_remember_key' => env('FLEET_IDP_SOCIALITE_TWO_FACTOR_REMEMBER_KEY', 'two_factor.remember'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Email code / magic link (Fleet Auth POST /api/email-login/*)
    |--------------------------------------------------------------------------
    |
    | Uses the same password-grant OAuth client id + secret. Magic links in email
    | point at magic_return_path on this app (or magic_return_url when set).
    |
    */

    'email_login' => [
        'http_timeout_seconds' => max(1, (int) env('FLEET_IDP_EMAIL_LOGIN_TIMEOUT', 10)),

        'magic_return_url' => env('FLEET_IDP_EMAIL_LOGIN_MAGIC_RETURN_URL'),

        'magic_return_path' => env('FLEET_IDP_EMAIL_LOGIN_MAGIC_PATH', '/login/email-magic'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Email sign-in on this satellite (profile toggle + /login/email-code UI)
    |--------------------------------------------------------------------------
    |
    | Fleet-linked users: send/verify via Fleet Auth APIs (password client). Local users:
    | challenges in challenges_table + mail notifications from this package.
    | Register the magic link route here, or set register_magic_route=false and define it
    | in your app. Override EmailSignInSessionCompleter to customize 2FA / post-login.
    |
    */

    'email_sign_in' => [
        'register_magic_route' => filter_var(env('FLEET_IDP_EMAIL_SIGN_IN_REGISTER_MAGIC_ROUTE', true), FILTER_VALIDATE_BOOL),

        'middleware' => array_values(array_filter(array_map('trim', explode(',', (string) env(
            'FLEET_IDP_EMAIL_SIGN_IN_MIDDLEWARE',
            'web,guest'
        ))))),

        'paths' => [
            'magic_login' => env('FLEET_IDP_EMAIL_SIGN_IN_MAGIC_PATH', 'login/email-magic'),
        ],

        'route_names' => [
            'magic_login' => env('FLEET_IDP_EMAIL_SIGN_IN_MAGIC_ROUTE_NAME', 'login.email-magic'),
        ],

        /*
         * Guest route to the email code / magic link sign-in UI (registered by the satellite app).
         */
        'guest_email_code_route_name' => env('FLEET_IDP_EMAIL_SIGN_IN_GUEST_EMAIL_CODE_ROUTE', 'login.email-code'),

        /*
         * Show the “sign in without password” card on the login page even when Fleet policy
         * does not advertise code/magic (local-only passwordless satellites).
         */
        'login_card_without_fleet_delivery' => filter_var(env('FLEET_IDP_EMAIL_SIGN_IN_LOGIN_CARD_WITHOUT_FLEET', false), FILTER_VALIDATE_BOOL),

        'user_enabled_attribute' => env('FLEET_IDP_EMAIL_SIGN_IN_USER_FLAG', 'email_code_login_enabled'),

        /*
        | Optional per-delivery columns on user_model. When null, code and magic link both use
        | user_enabled_attribute above (legacy). Set both to split profile toggles (e.g. email_login_code_enabled
        | and email_login_magic_link_enabled).
        */
        'user_code_enabled_attribute' => env('FLEET_IDP_EMAIL_SIGN_IN_CODE_USER_FLAG') ?: null,

        'user_magic_link_enabled_attribute' => env('FLEET_IDP_EMAIL_SIGN_IN_MAGIC_USER_FLAG') ?: null,

        'challenges_table' => env('FLEET_IDP_EMAIL_SIGN_IN_CHALLENGES_TABLE', 'local_email_login_challenges'),

        'load_migrations' => filter_var(env('FLEET_IDP_EMAIL_SIGN_IN_LOAD_MIGRATIONS', true), FILTER_VALIDATE_BOOL),

        'local_code_ttl_minutes' => max(1, (int) env('FLEET_IDP_EMAIL_SIGN_IN_CODE_TTL', 10)),

        'local_magic_link_ttl_minutes' => max(1, (int) env('FLEET_IDP_EMAIL_SIGN_IN_MAGIC_TTL', 30)),

        /*
        | When true, a user may have only one of code or magic link enabled (plus at most one
        | pending confirmation). Ignored when code and magic share the same user attribute
        | (legacy single flag). Satellites opt in via config or env.
        */
        'mutually_exclusive_code_and_magic' => filter_var(env('FLEET_IDP_EMAIL_SIGN_IN_EXCLUSIVE', false), FILTER_VALIDATE_BOOL),

        /*
        |--------------------------------------------------------------------------
        | Profile: confirm email before enabling code / magic (satellite)
        |--------------------------------------------------------------------------
        |
        | When users turn on one-time codes or magic links in profile, the option
        | stays off until they open a signed link in email. Routes, user columns,
        | and mail copy live in this package; disable here to register routes in
        | your app instead.
        |
        */

        'profile_confirm' => [
            'enabled' => filter_var(env('FLEET_IDP_PROFILE_EMAIL_SIGN_IN_CONFIRM_ENABLED', true), FILTER_VALIDATE_BOOL),

            'middleware' => array_values(array_filter(array_map('trim', explode(',', (string) env(
                'FLEET_IDP_PROFILE_EMAIL_SIGN_IN_CONFIRM_MIDDLEWARE',
                'web'
            ))))),

            /*
             * Rate limit for GET confirm links, e.g. "6,1". Empty string = no throttle middleware.
             */
            'throttle' => env('FLEET_IDP_PROFILE_EMAIL_SIGN_IN_CONFIRM_THROTTLE', '6,1'),

            'token_ttl_hours' => max(1, (int) env('FLEET_IDP_PROFILE_EMAIL_SIGN_IN_CONFIRM_TTL_HOURS', 24)),

            'paths' => [
                'magic' => env('FLEET_IDP_PROFILE_CONFIRM_MAGIC_PATH', 'profile/confirm-magic-sign-in'),
                'code' => env('FLEET_IDP_PROFILE_CONFIRM_CODE_PATH', 'profile/confirm-email-code-sign-in'),
            ],

            'route_names' => [
                'magic' => env('FLEET_IDP_PROFILE_CONFIRM_MAGIC_ROUTE_NAME', 'profile.confirm-magic-sign-in'),
                'code' => env('FLEET_IDP_PROFILE_CONFIRM_CODE_ROUTE_NAME', 'profile.confirm-email-code-sign-in'),
            ],

            'redirect_route_when_authenticated' => env('FLEET_IDP_PROFILE_CONFIRM_REDIRECT_AUTHED', 'profile'),

            'redirect_route_when_guest' => env('FLEET_IDP_PROFILE_CONFIRM_REDIRECT_GUEST', 'login'),

            /*
             | Blade layout for the GET interstitial (email link → confirm button).
             | Use your app layout (e.g. layouts.guest) for branding; default is package minimal.
             */
            'interstitial_layout' => env('FLEET_IDP_PROFILE_CONFIRM_INTERSTITIAL_LAYOUT', 'fleet-idp::layouts.minimal'),

            /*
             | DB columns on user_model. Changing these requires a matching migration in your app.
             */
            'columns' => [
                'magic_pending_token_hash' => env(
                    'FLEET_IDP_USER_MAGIC_PENDING_TOKEN_HASH',
                    'magic_link_sign_in_pending_token_hash'
                ),
                'magic_pending_expires_at' => env(
                    'FLEET_IDP_USER_MAGIC_PENDING_EXPIRES',
                    'magic_link_sign_in_pending_expires_at'
                ),
                'code_pending_token_hash' => env(
                    'FLEET_IDP_USER_CODE_PENDING_TOKEN_HASH',
                    'email_code_sign_in_pending_token_hash'
                ),
                'code_pending_expires_at' => env(
                    'FLEET_IDP_USER_CODE_PENDING_EXPIRES',
                    'email_code_sign_in_pending_expires_at'
                ),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Account: forgot / reset / change password (package routes)
    |--------------------------------------------------------------------------
    |
    | Forgot password is always served on this app: we look up fleet_idp.user_model by
    | email. Fleet-linked users and emails found only on Fleet Auth see a confirm step,
    | then POST forgot-password/fleet-send (route_names.forgot_fleet_send) to trigger
    | provisioning. Otherwise Laravel’s Password broker sends a local reset link. Change password:
    | the package route may redirect Fleet-linked users to the IdP; apps can instead call
    | FleetIdp::attemptFleetPasswordChange (POST /api/provisioning/users/password-change) from profile UI.
    |
    */

    'account' => [
        'enabled' => filter_var(env('FLEET_IDP_ACCOUNT_ROUTES_ENABLED', true), FILTER_VALIDATE_BOOL),

        'local_password_only' => filter_var(env('FLEET_IDP_LOCAL_PASSWORD_ONLY', false), FILTER_VALIDATE_BOOL),

        'middleware' => array_values(array_filter(array_map('trim', explode(',', (string) env(
            'FLEET_IDP_ACCOUNT_MIDDLEWARE',
            'web'
        ))))),

        'route_prefix' => env('FLEET_IDP_ACCOUNT_ROUTE_PREFIX', ''),

        /*
        | When true, the package uses layouts.fleet-idp-account if that view exists and
        | layout is still the default minimal package layout (see FLEET_IDP_ACCOUNT_LAYOUT).
        */
        'auto_layout' => filter_var(env('FLEET_IDP_ACCOUNT_AUTO_LAYOUT', true), FILTER_VALIDATE_BOOL),

        'layout' => env('FLEET_IDP_ACCOUNT_LAYOUT', 'fleet-idp::layouts.minimal'),

        /*
        | Optional app-owned Blade for forgot-password (default: package view). Set to e.g.
        | auth.forgot-password so the satellite keeps one view that reads session keys from
        | {@see \Fleet\IdpClient\Http\Controllers\Account\LocalForgotPasswordController}.
        */
        'views' => [
            'forgot_password' => env('FLEET_IDP_ACCOUNT_VIEW_FORGOT_PASSWORD', 'fleet-idp::account.forgot-password'),
            /*
            | Optional app-owned Blade for reset-password (token form). Default: package view.
            */
            'reset_password' => env('FLEET_IDP_ACCOUNT_VIEW_RESET_PASSWORD', 'fleet-idp::account.reset-password'),
        ],

        /*
        | Comma-separated email domains (e.g. "fleet.test,fleet-auth.test") that always show
        | the Fleet reset confirmation step for forgot-password, even when the user is not
        | Fleet-linked or provisioning lookup misses. Empty = disabled.
        */
        'likely_email_domains' => array_values(array_filter(array_map(
            static fn (string $d): string => strtolower(trim($d)),
            explode(',', (string) env('FLEET_IDP_LIKELY_EMAIL_DOMAINS', ''))
        ))),

        'after_reset_route' => env('FLEET_IDP_ACCOUNT_AFTER_RESET_ROUTE', 'login'),

        'idp_paths' => [
            'forgot_password' => env('FLEET_IDP_IDP_FORGOT_PASSWORD_PATH', '/forgot-password'),
            'reset_password' => env('FLEET_IDP_IDP_RESET_PASSWORD_PATH', '/reset-password/{token}'),
            'change_password' => env('FLEET_IDP_IDP_CHANGE_PASSWORD_PATH', '/account/password'),
        ],

        'route_names' => [
            'forgot_request' => env('FLEET_IDP_ROUTE_PASSWORD_REQUEST', 'password.request'),
            'forgot_store' => env('FLEET_IDP_ROUTE_PASSWORD_EMAIL', 'password.email'),
            'forgot_fleet_send' => env('FLEET_IDP_ROUTE_PASSWORD_EMAIL_FLEET', 'password.email.fleet'),
            'reset_show' => env('FLEET_IDP_ROUTE_PASSWORD_RESET', 'password.reset'),
            'reset_store' => env('FLEET_IDP_ROUTE_PASSWORD_UPDATE', 'password.update'),
            'change_show' => env('FLEET_IDP_ROUTE_ACCOUNT_PASSWORD', 'fleet-idp.account.password.edit'),
            'change_update' => env('FLEET_IDP_ROUTE_ACCOUNT_PASSWORD_UPDATE', 'fleet-idp.account.password.update'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Satellite session policy (middleware)
    |--------------------------------------------------------------------------
    |
    | Uses FleetSocialLoginPolicy (cached GET /api/social-login/providers). Register in your app
    | bootstrap via Fleet\IdpClient\Http\FleetSatelliteWebMiddleware::register($middleware).
    |
    */

    'satellite_middleware' => [
        'require_two_factor_redirect_route' => env('FLEET_IDP_SATELLITE_REQUIRE_2FA_REDIRECT_ROUTE', 'profile'),

        'require_two_factor_extra_exempt_route_names' => [],
    ],

];
