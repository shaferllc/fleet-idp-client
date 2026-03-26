<?php

/**
 * Optional overrides merged on top of the package `fleet_idp` config (no need to publish the full `fleet_idp.php`).
 *
 * Publish: php artisan vendor:publish --tag=fleet-idp-overrides
 *          (also included in --tag=fleet-idp-config and fleet:idp:install --with-config)
 *
 * In AppServiceProvider::register():
 *   FleetIdpCustomization::merge(require config_path('fleet_idp_overrides.php'));
 *
 * Env keys match the package; defaults below suit Fleet Console–style apps (session OAuth, /auth/callback, trusted IP).
 */

return [
    'redirect_path' => env('FLEET_IDP_REDIRECT_PATH', '/auth/callback'),
    'web' => [
        'middleware' => array_values(array_filter(array_map('trim', explode(',', (string) env(
            'FLEET_IDP_WEB_MIDDLEWARE',
            'web,fleet.trusted_ip'
        ))))),
        'mode' => env('FLEET_IDP_WEB_MODE', 'session'),
        'eloquent' => [
            'try_again_route' => env('FLEET_IDP_TRY_AGAIN_ROUTE', 'console.login'),
            'post_login_route' => env('FLEET_IDP_POST_LOGIN_ROUTE', 'console.dashboard'),
        ],
    ],
    'socialite' => [
        'error_route' => env('FLEET_IDP_SOCIALITE_ERROR_ROUTE', 'console.login'),
        'post_login_route' => env('FLEET_IDP_SOCIALITE_POST_LOGIN_ROUTE', 'console.dashboard'),
    ],
];
