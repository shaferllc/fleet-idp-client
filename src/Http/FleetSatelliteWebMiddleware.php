<?php

declare(strict_types=1);

namespace Fleet\IdpClient\Http;

use Fleet\IdpClient\Http\Middleware\EnsureFleetSiteRequiresTwoFactor;
use Fleet\IdpClient\Http\Middleware\EnsureSatelliteEmailIsVerified;
use Illuminate\Foundation\Configuration\Middleware;

/**
 * Registers Fleet-controlled satellite session policy in the HTTP kernel.
 *
 * Call from `bootstrap/app.php` inside `Application::configure()->withMiddleware(...)`.
 */
final class FleetSatelliteWebMiddleware
{
    public static function register(Middleware $middleware): void
    {
        $middleware->alias([
            'verified' => EnsureSatelliteEmailIsVerified::class,
        ]);
        $middleware->appendToGroup('web', [
            EnsureFleetSiteRequiresTwoFactor::class,
        ]);
    }
}
