<?php

declare(strict_types=1);

use Fleet\IdpClient\Http\Controllers\FleetIdpOAuthWebController;
use Illuminate\Support\Facades\Route;

if (! config('fleet_idp.web.enabled', true)) {
    return;
}

$middleware = config('fleet_idp.web.middleware', ['web']);
$middleware = is_array($middleware) ? $middleware : [$middleware];

$normalize = static fn (string $path): string => '/'.ltrim(trim($path), '/');

$startPath = $normalize((string) config('fleet_idp.web.start_path', '/oauth/fleet-auth'));
$callbackPath = $normalize((string) config('fleet_idp.redirect_path', '/oauth/fleet-auth/callback'));

$redirectName = (string) config('fleet_idp.web.route_names.redirect', 'fleet-idp.oauth.redirect');
$callbackName = (string) config('fleet_idp.web.route_names.callback', 'fleet-idp.oauth.callback');
$failurePath = $normalize((string) config('fleet_idp.web.failure_path', '/oauth/fleet-auth/failure'));
$failureName = (string) config('fleet_idp.web.route_names.failure', 'fleet-idp.oauth.failure');

Route::middleware($middleware)->group(static function () use ($startPath, $callbackPath, $failurePath, $redirectName, $callbackName, $failureName): void {
    Route::get($startPath, [FleetIdpOAuthWebController::class, 'redirect'])->name($redirectName);
    Route::get($callbackPath, [FleetIdpOAuthWebController::class, 'callback'])->name($callbackName);
    Route::get($failurePath, [FleetIdpOAuthWebController::class, 'failure'])->name($failureName);
});
