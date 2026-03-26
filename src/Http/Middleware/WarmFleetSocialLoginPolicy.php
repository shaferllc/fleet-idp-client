<?php

declare(strict_types=1);

namespace Fleet\IdpClient\Http\Middleware;

use Closure;
use Fleet\IdpClient\Services\FleetSocialLoginPolicy;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves {@see FleetSocialLoginPolicy::snapshot()} at the start of each web request so
 * satellites match Fleet Auth before controllers/views run. Uses the same cache TTL as
 * {@see FleetSocialLoginPolicy} (fleet_idp.socialite.policy_cache_seconds).
 */
final class WarmFleetSocialLoginPolicy
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->runningUnitTests()) {
            return $next($request);
        }

        if (! filter_var(config('fleet_idp.socialite.enabled', true), FILTER_VALIDATE_BOOL)) {
            return $next($request);
        }

        $base = rtrim((string) config('fleet_idp.url', ''), '/');
        if ($base === '') {
            return $next($request);
        }

        try {
            FleetSocialLoginPolicy::snapshot();
        } catch (\Throwable) {
            // Unreachable IdP: snapshot applies fail-open / safe defaults.
        }

        return $next($request);
    }
}
