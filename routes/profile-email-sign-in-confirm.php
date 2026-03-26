<?php

declare(strict_types=1);

use Fleet\IdpClient\Http\Controllers\ConfirmProfileEmailSignInController;
use Illuminate\Support\Facades\Route;

if (! filter_var(config('fleet_idp.email_sign_in.profile_confirm.enabled', true), FILTER_VALIDATE_BOOL)) {
    return;
}

$middleware = config('fleet_idp.email_sign_in.profile_confirm.middleware', ['web']);
$middleware = is_array($middleware) ? $middleware : [$middleware];

$throttle = config('fleet_idp.email_sign_in.profile_confirm.throttle');
if (is_string($throttle) && trim($throttle) !== '') {
    $middleware[] = 'throttle:'.trim($throttle);
}

$magicPath = ltrim((string) config(
    'fleet_idp.email_sign_in.profile_confirm.paths.magic',
    'profile/confirm-magic-sign-in'
), '/');
$codePath = ltrim((string) config(
    'fleet_idp.email_sign_in.profile_confirm.paths.code',
    'profile/confirm-email-code-sign-in'
), '/');
$magicName = (string) config(
    'fleet_idp.email_sign_in.profile_confirm.route_names.magic',
    'profile.confirm-magic-sign-in'
);
$codeName = (string) config(
    'fleet_idp.email_sign_in.profile_confirm.route_names.code',
    'profile.confirm-email-code-sign-in'
);

Route::middleware($middleware)->group(static function () use ($magicPath, $codePath, $magicName, $codeName): void {
    Route::match(['get', 'post'], $magicPath, ConfirmProfileEmailSignInController::class)
        ->name($magicName)
        ->defaults('fleet_idp_profile_confirm_kind', 'magic');

    Route::match(['get', 'post'], $codePath, ConfirmProfileEmailSignInController::class)
        ->name($codeName)
        ->defaults('fleet_idp_profile_confirm_kind', 'code');
});
