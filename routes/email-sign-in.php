<?php

use Fleet\IdpClient\Http\Controllers\EmailMagicLoginController;
use Illuminate\Support\Facades\Route;

if (! filter_var(config('fleet_idp.email_sign_in.register_magic_route', true), FILTER_VALIDATE_BOOL)) {
    return;
}

$middleware = config('fleet_idp.email_sign_in.middleware', ['web', 'guest']);
if (! is_array($middleware)) {
    $middleware = ['web', 'guest'];
}

Route::middleware($middleware)->group(function (): void {
    $path = ltrim((string) config('fleet_idp.email_sign_in.paths.magic_login', 'login/email-magic'), '/');
    $name = (string) config('fleet_idp.email_sign_in.route_names.magic_login', 'login.email-magic');

    Route::get($path, EmailMagicLoginController::class)->name($name);
});
