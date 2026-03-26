<?php

declare(strict_types=1);

namespace Fleet\IdpClient\Http\Middleware;

use Closure;
use Fleet\IdpClient\Services\FleetSocialLoginPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * When Fleet Auth policy requires verified email for this OAuth client, block routes that use
 * the `verified` middleware until the user has verified their email.
 */
final class EnsureSatelliteEmailIsVerified
{
    public function handle(Request $request, Closure $next, ?string $redirectToRoute = null): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if (! FleetSocialLoginPolicy::requireEmailVerification()) {
            return $next($request);
        }

        if (! method_exists($user, 'hasVerifiedEmail') || $user->hasVerifiedEmail()) {
            return $next($request);
        }

        return $request->expectsJson()
            ? abort(403, __('fleet-idp::satellite.email_not_verified_json'))
            : Redirect::guest(URL::route($redirectToRoute ?: 'verification.notice'));
    }
}
