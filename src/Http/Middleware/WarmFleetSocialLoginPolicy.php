<?php

declare(strict_types=1);

namespace Fleet\IdpClient\Http\Middleware;

use Closure;
use Fleet\IdpClient\Services\FleetSocialLoginPolicy;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Optionally resolves {@see FleetSocialLoginPolicy::snapshot()} at the start of a web request
 * when Fleet Auth IdP settings enable “warm providers each request” (after cache is seeded).
 */
final class WarmFleetSocialLoginPolicy
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! FleetSocialLoginPolicy::shouldWarmPolicyOnThisRequest()) {
            return $next($request);
        }

        try {
            FleetSocialLoginPolicy::snapshot();
        } catch (\Throwable) {
            // Unreachable IdP: snapshot uses transport-safe defaults.
        }

        return $next($request);
    }
}
