<?php

declare(strict_types=1);

namespace Fleet\IdpClient\Http\Controllers;

use Fleet\IdpClient\Contracts\EmailSignInSessionCompleter;
use Fleet\IdpClient\FleetIdpEloquentUserProvisioner;
use Fleet\IdpClient\FleetIdpEmailLogin;
use Fleet\IdpClient\Services\FleetSocialLoginPolicy;
use Fleet\IdpClient\Services\LocalEmailLoginService;
use Fleet\IdpClient\Support\EmailSignInUserOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class EmailMagicLoginController extends Controller
{
    public function __invoke(Request $request, LocalEmailLoginService $localMagic, EmailSignInSessionCompleter $session): RedirectResponse
    {
        $token = $request->query('token');
        if (! is_string($token) || trim($token) === '') {
            return redirect()->route('login')
                ->with('status', __('Missing sign-in link.'));
        }

        $token = trim($token);

        $user = $localMagic->verifyMagicToken($token);
        if ($user !== null) {
            if (! EmailSignInUserOptions::userAllowsMagicLink($user)) {
                return redirect()->route('login')
                    ->with('status', __('This sign-in link is invalid or has expired.'));
            }

            $next = $session->complete($user, true);

            if ($next['mode'] === 'two_factor') {
                return redirect()->to($next['url']);
            }

            return redirect()->intended($next['url']);
        }

        if (! FleetIdpEmailLogin::isAvailable() || ! FleetSocialLoginPolicy::emailLoginMagicLinkAllowed()) {
            return redirect()->route('login')
                ->with('status', __('This sign-in link is invalid or has expired.'));
        }

        $remote = FleetIdpEmailLogin::verifyToken($token);
        if ($remote === null) {
            return redirect()->route('login')
                ->with('status', __('This sign-in link is invalid or has expired.'));
        }

        $sync = FleetIdpEloquentUserProvisioner::syncFromRemoteUser($remote);
        if ($sync['error'] !== null || $sync['user'] === null) {
            return redirect()->route('login')
                ->with('status', is_string($sync['error']) ? $sync['error'] : __('Could not complete sign-in.'));
        }

        $synced = $sync['user'];
        if (! EmailSignInUserOptions::userAllowsMagicLink($synced)) {
            return redirect()->route('login')
                ->with('status', __('This sign-in link is invalid or has expired.'));
        }

        $next = $session->complete($synced, true);

        if ($next['mode'] === 'two_factor') {
            return redirect()->to($next['url']);
        }

        return redirect()->intended($next['url']);
    }
}
