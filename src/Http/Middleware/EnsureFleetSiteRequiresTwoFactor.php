<?php

declare(strict_types=1);

namespace Fleet\IdpClient\Http\Middleware;

use Closure;
use Fleet\IdpClient\Services\FleetSocialLoginPolicy;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * When Fleet Auth policy requires 2FA for this OAuth client, block normal app pages until the
 * user completes local two-factor setup (TOTP), except exempt routes (profile, verification, …).
 */
final class EnsureFleetSiteRequiresTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if (! FleetSocialLoginPolicy::requireTwoFactor()) {
            return $next($request);
        }

        if (method_exists($user, 'hasTwoFactorEnabled') && $user->hasTwoFactorEnabled()) {
            return $next($request);
        }

        if ($this->isExempt($request)) {
            return $next($request);
        }

        $route = (string) config('fleet_idp.satellite_middleware.require_two_factor_redirect_route', 'profile');

        return redirect()
            ->route($route)
            ->with('status', __('fleet-idp::satellite.require_two_factor_flash'));
    }

    private function isExempt(Request $request): bool
    {
        $defaults = [
            'profile',
            'verification.*',
            'password.confirm',
            'default.livewire.*',
            'livewire.upload-file',
            'livewire.preview-file',
        ];

        $extra = config('fleet_idp.satellite_middleware.require_two_factor_extra_exempt_route_names', []);

        if (! is_array($extra)) {
            $extra = [];
        }

        return $request->routeIs(array_merge($defaults, $extra));
    }
}
