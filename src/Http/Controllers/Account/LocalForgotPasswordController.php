<?php

declare(strict_types=1);

namespace Fleet\IdpClient\Http\Controllers\Account;

use Fleet\IdpClient\Support\FleetLikelyAccountEmail;
use Fleet\IdpClient\Support\FleetPasswordRouting;
use Fleet\IdpClient\Support\FleetProvisioningPasswordReset;
use Fleet\IdpClient\Support\FleetProvisioningUserLookup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LocalForgotPasswordController extends Controller
{
    public function create(Request $request): View
    {
        if ($request->query('restart')) {
            session()->forget([
                'fleet_idp_pending_fleet_reset',
                'fleet_idp_fleet_reset_confirm',
            ]);
        }

        $view = config('fleet_idp.account.views.forgot_password');
        $view = is_string($view) && $view !== '' ? $view : 'fleet-idp::account.forgot-password';

        return view($view);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = Str::lower(trim($request->string('email')->toString()));
        $modelClass = config('fleet_idp.user_model');

        if (! is_string($modelClass) || ! class_exists($modelClass)) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => trans('fleet-idp::account.reset_failed')]);
        }

        $user = $modelClass::query()->where('email', $email)->first();

        if ($user !== null && FleetPasswordRouting::userPasswordManagedByIdp($user)) {
            return $this->redirectToFleetSendConfirmation($email, 'linked', 'standard');
        }

        if ($user !== null && $this->shouldConfirmFleetResetForLikelyFleetEmail($email)) {
            return $this->redirectToFleetSendConfirmation($email, 'linked', 'likely_domain');
        }

        if ($user !== null) {
            $status = Password::sendResetLink(['email' => $email]);

            return $status === Password::RESET_LINK_SENT
                ? back()->with('status', trans('fleet-idp::account.reset_link_sent'))
                : back()->withInput($request->only('email'))->withErrors(['email' => __($status)]);
        }

        if ($user === null) {
            $fleetBranch = $this->fleetOnlyEmailBranch($email);
            if ($fleetBranch !== null) {
                return $fleetBranch;
            }
        }

        return back()->with('status', trans('fleet-idp::account.reset_link_sent'));
    }

    /**
     * Confirms sending a Fleet Auth reset after the user acknowledged the Fleet flow.
     */
    public function sendThroughFleet(Request $request): RedirectResponse
    {
        $pending = $request->session()->get('fleet_idp_fleet_reset_confirm');
        if (! is_array($pending)) {
            return redirect()
                ->route(config('fleet_idp.account.route_names.forgot_request', 'password.request'))
                ->withErrors(['email' => trans('fleet-idp::account.fleet_reset_confirm_expired')]);
        }

        $email = Str::lower(trim((string) ($pending['email'] ?? '')));
        $source = (string) ($pending['source'] ?? '');
        if ($email === '' || ! in_array($source, ['linked', 'fleet_only'], true)) {
            $request->session()->forget('fleet_idp_fleet_reset_confirm');

            return redirect()
                ->route(config('fleet_idp.account.route_names.forgot_request', 'password.request'))
                ->withErrors(['email' => trans('fleet-idp::account.fleet_reset_confirm_expired')]);
        }

        $request->session()->forget('fleet_idp_fleet_reset_confirm');

        return $this->sendFleetPasswordResetOrFallback($email, $source);
    }

    /**
     * No local user: optionally check Fleet via provisioning. Returns null to fall through to generic success.
     */
    private function fleetOnlyEmailBranch(string $email): ?RedirectResponse
    {
        if (FleetPasswordRouting::idpBaseUrl() === '' || FleetPasswordRouting::localPasswordOnly()) {
            return null;
        }

        $token = config('fleet_idp.provisioning.token');
        $hasProvisioning = is_string($token) && $token !== '';

        if ($hasProvisioning) {
            $onFleet = FleetProvisioningUserLookup::emailExistsOnFleet($email);
            if ($onFleet === true) {
                return $this->redirectToFleetSendConfirmation($email, 'fleet_only', 'standard');
            }
        }

        if ($this->shouldConfirmFleetResetForLikelyFleetEmail($email)) {
            return $this->redirectToFleetSendConfirmation($email, 'fleet_only', 'likely_domain');
        }

        return null;
    }

    /**
     * @param  'linked'|'fleet_only'  $source
     * @param  'standard'|'likely_domain'  $prompt
     */
    private function redirectToFleetSendConfirmation(string $email, string $source, string $prompt = 'standard'): RedirectResponse
    {
        // Session put, not flash: redirect()->with() only survives one request; browsers GET
        // forgot-password after POST before POST forgot-password/fleet-send.
        session()->put('fleet_idp_fleet_reset_confirm', [
            'email' => $email,
            'source' => $source,
            'prompt' => $prompt,
        ]);

        return back();
    }

    private function shouldConfirmFleetResetForLikelyFleetEmail(string $email): bool
    {
        if (FleetPasswordRouting::localPasswordOnly() || FleetPasswordRouting::idpBaseUrl() === '') {
            return false;
        }

        return FleetLikelyAccountEmail::emailLooksLikeFleetAccount($email);
    }

    /**
     * Ask Fleet Auth to send the reset email (user stays on this app). When that fails:
     * - For a local Fleet-linked row, try this app’s password broker so reset can complete here.
     * - For fleet_only (no local user), fall back to the manual “open Fleet” confirmation.
     *
     * @param  'linked'|'fleet_only'  $source
     */
    private function sendFleetPasswordResetOrFallback(string $email, string $source): RedirectResponse
    {
        $provision = FleetProvisioningPasswordReset::attempt($email);
        if ($provision['ok'] === true) {
            return back()->with('status', trans('fleet-idp::account.fleet_reset_link_sent'));
        }

        if ($source === 'linked') {
            $status = Password::sendResetLink(['email' => $email]);
            if ($status === Password::RESET_LINK_SENT) {
                return back()->with('status', trans('fleet-idp::account.reset_link_sent'));
            }

            return back()
                ->withInput(['email' => $email])
                ->withErrors(['email' => __($status)]);
        }

        return back()
            ->withInput(['email' => $email])
            ->with('fleet_idp_pending_fleet_reset', [
                'email' => $email,
                'url' => FleetPasswordRouting::idpForgotPasswordUrlWithEmail($email),
                'source' => $source,
                'reason' => 'api_unavailable',
                'provision_error' => $provision['error'],
                'provision_http_status' => $provision['http_status'],
            ]);
    }
}
