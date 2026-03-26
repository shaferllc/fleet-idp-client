<?php

declare(strict_types=1);

use Fleet\IdpClient\Http\Controllers\SocialiteOAuthController;
use Illuminate\Support\Facades\Route;

if (! filter_var(config('fleet_idp.socialite.enabled', false), FILTER_VALIDATE_BOOL)) {
    return;
}

$middleware = config('fleet_idp.socialite.middleware', ['web']);
$middleware = is_array($middleware) ? $middleware : [$middleware];

$prefix = trim((string) config('fleet_idp.socialite.route_prefix', 'oauth'), '/');
$prefix = $prefix === '' ? 'oauth' : $prefix;

Route::middleware($middleware)
    ->prefix($prefix)
    ->group(static function (): void {
        Route::get('{provider}', [SocialiteOAuthController::class, 'redirect'])
            ->whereIn('provider', ['github', 'google'])
            ->name('fleet-idp.socialite.redirect');

        Route::get('{provider}/callback', [SocialiteOAuthController::class, 'callback'])
            ->whereIn('provider', ['github', 'google'])
            ->name('fleet-idp.socialite.callback');
    });
