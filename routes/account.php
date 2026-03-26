<?php

declare(strict_types=1);

use Fleet\IdpClient\Http\Controllers\Account\LocalChangePasswordController;
use Fleet\IdpClient\Http\Controllers\Account\LocalForgotPasswordController;
use Fleet\IdpClient\Http\Controllers\Account\LocalResetPasswordController;
use Illuminate\Support\Facades\Route;

if (! filter_var(config('fleet_idp.account.enabled', true), FILTER_VALIDATE_BOOL)) {
    return;
}

$middleware = config('fleet_idp.account.middleware');
$middleware = is_array($middleware) ? $middleware : ['web'];
$guestMiddleware = array_values(array_unique(array_merge($middleware, ['guest'])));
$authMiddleware = array_values(array_unique(array_merge($middleware, ['auth'])));

$prefix = trim((string) config('fleet_idp.account.route_prefix', ''), '/');
$prefix = $prefix === '' ? '' : $prefix.'/';

$names = config('fleet_idp.account.route_names');
$names = is_array($names) ? $names : [];
$forgotGet = (string) ($names['forgot_request'] ?? 'password.request');
$forgotPost = (string) ($names['forgot_store'] ?? 'password.email');
$forgotFleetSend = (string) ($names['forgot_fleet_send'] ?? 'password.email.fleet');
$resetGet = (string) ($names['reset_show'] ?? 'password.reset');
$resetPost = (string) ($names['reset_store'] ?? 'password.update');
$changeGet = (string) ($names['change_show'] ?? 'fleet-idp.account.password.edit');
$changePut = (string) ($names['change_update'] ?? 'fleet-idp.account.password.update');

Route::middleware($guestMiddleware)->group(function () use ($prefix, $forgotGet, $forgotPost, $forgotFleetSend, $resetGet, $resetPost): void {
    Route::get($prefix.'forgot-password', [LocalForgotPasswordController::class, 'create'])
        ->name($forgotGet);
    Route::post($prefix.'forgot-password', [LocalForgotPasswordController::class, 'store'])
        ->name($forgotPost);
    Route::post($prefix.'forgot-password/fleet-send', [LocalForgotPasswordController::class, 'sendThroughFleet'])
        ->name($forgotFleetSend);
    Route::get($prefix.'reset-password/{token}', [LocalResetPasswordController::class, 'create'])
        ->name($resetGet);
    Route::post($prefix.'reset-password', [LocalResetPasswordController::class, 'store'])
        ->name($resetPost);
});

Route::middleware($authMiddleware)->group(function () use ($prefix, $changeGet, $changePut): void {
    Route::get($prefix.'account/password', [LocalChangePasswordController::class, 'create'])
        ->name($changeGet);
    Route::put($prefix.'account/password', [LocalChangePasswordController::class, 'update'])
        ->name($changePut);
});
