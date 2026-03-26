<?php

declare(strict_types=1);

namespace Fleet\IdpClient\Http\Controllers;

use Fleet\IdpClient\Support\ProfileEmailSignInConfirmation;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Confirms profile email-sign-in toggles: GET shows an interstitial (token in email link);
 * POST applies the change so link prefetchers cannot consume the token.
 *
 * Route default {@see fleet_idp_profile_confirm_kind}: magic | code
 */
class ConfirmProfileEmailSignInController
{
    public function __invoke(Request $request): RedirectResponse|View
    {
        $kind = (string) $request->route()->parameter('fleet_idp_profile_confirm_kind');

        $spec = match ($kind) {
            'magic' => ProfileEmailSignInConfirmation::magicLinkConfirmationFieldMap(),
            'code' => ProfileEmailSignInConfirmation::emailCodeConfirmationFieldMap(),
            default => null,
        };

        if ($spec === null) {
            abort(404);
        }

        return $request->isMethod('post')
            ? $this->handlePost($request, $kind, $spec)
            : $this->handleGet($request, $kind, $spec);
    }

    private function handleGet(Request $request, string $kind, array $spec): RedirectResponse|View
    {
        $token = $request->query('token');
        if (! is_string($token) || strlen($token) < 32) {
            return $this->afterConfirm(false, __('fleet-idp::email_sign_in.profile_confirm_invalid'));
        }

        $modelClass = $this->userModelClass();
        $hash = hash('sha256', $token);

        /** @var Model|null $user */
        $user = $modelClass::query()
            ->where($spec['pending_hash'], $hash)
            ->first();

        if ($user === null) {
            return $this->afterConfirm(false, __('fleet-idp::email_sign_in.profile_confirm_used'));
        }

        $expires = $this->normalizeExpires($user->getAttribute($spec['pending_expires']));
        if ($expires === null || $expires->isPast()) {
            $this->clearPending($user, $spec);

            $expiredMessage = $kind === 'magic'
                ? __('fleet-idp::email_sign_in.profile_confirm_expired_magic')
                : __('fleet-idp::email_sign_in.profile_confirm_expired_code');

            return $this->afterConfirm(false, $expiredMessage);
        }

        $routeName = (string) $request->route()->getName();

        return view('fleet-idp::profile-email-sign-in-confirm', [
            'title' => $kind === 'magic'
                ? __('fleet-idp::email_sign_in.profile_confirm_page_title_magic')
                : __('fleet-idp::email_sign_in.profile_confirm_page_title_code'),
            'lead' => $kind === 'magic'
                ? __('fleet-idp::email_sign_in.profile_confirm_page_lead_magic')
                : __('fleet-idp::email_sign_in.profile_confirm_page_lead_code'),
            'buttonLabel' => $kind === 'magic'
                ? __('fleet-idp::email_sign_in.profile_confirm_page_button_magic')
                : __('fleet-idp::email_sign_in.profile_confirm_page_button_code'),
            'routeName' => $routeName,
            'token' => $token,
        ]);
    }

    private function handlePost(Request $request, string $kind, array $spec): RedirectResponse
    {
        $token = $request->input('token');
        if (! is_string($token) || strlen($token) < 32) {
            return $this->afterConfirm(false, __('fleet-idp::email_sign_in.profile_confirm_invalid'));
        }

        $modelClass = $this->userModelClass();
        $hash = hash('sha256', $token);

        /** @var Model|null $user */
        $user = $modelClass::query()
            ->where($spec['pending_hash'], $hash)
            ->first();

        if ($user === null) {
            return $this->afterConfirm(false, __('fleet-idp::email_sign_in.profile_confirm_used'));
        }

        $expires = $this->normalizeExpires($user->getAttribute($spec['pending_expires']));
        if ($expires === null || $expires->isPast()) {
            $this->clearPending($user, $spec);

            $expiredMessage = $kind === 'magic'
                ? __('fleet-idp::email_sign_in.profile_confirm_expired_magic')
                : __('fleet-idp::email_sign_in.profile_confirm_expired_code');

            return $this->afterConfirm(false, $expiredMessage);
        }

        if (Auth::check() && (int) Auth::id() !== (int) $user->getKey()) {
            $profileRoute = (string) config(
                'fleet_idp.email_sign_in.profile_confirm.redirect_route_when_authenticated',
                'profile'
            );

            return redirect()
                ->route($profileRoute)
                ->with('error', __('fleet-idp::email_sign_in.profile_confirm_wrong_account'));
        }

        $user->forceFill([
            $spec['enabled'] => true,
            $spec['pending_hash'] => null,
            $spec['pending_expires'] => null,
        ])->save();

        $successMessage = $kind === 'magic'
            ? __('fleet-idp::email_sign_in.profile_confirm_magic_success')
            : __('fleet-idp::email_sign_in.profile_confirm_code_success');

        return $this->afterConfirm(true, $successMessage);
    }

    /**
     * @return class-string<Model>
     */
    private function userModelClass(): string
    {
        $modelClass = (string) config('fleet_idp.user_model', 'App\\Models\\User');
        if (! class_exists($modelClass) || ! is_subclass_of($modelClass, Model::class)) {
            abort(500);
        }

        return $modelClass;
    }

    private function normalizeExpires(mixed $expires): ?Carbon
    {
        if ($expires === null) {
            return null;
        }

        try {
            return Carbon::parse($expires);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array{pending_hash: string, pending_expires: string, enabled: string}  $spec
     */
    private function clearPending(Model $user, array $spec): void
    {
        $user->forceFill([
            $spec['pending_hash'] => null,
            $spec['pending_expires'] => null,
        ])->save();
    }

    private function afterConfirm(bool $success, string $message): RedirectResponse
    {
        $authedRoute = (string) config(
            'fleet_idp.email_sign_in.profile_confirm.redirect_route_when_authenticated',
            'profile'
        );
        $guestRoute = (string) config(
            'fleet_idp.email_sign_in.profile_confirm.redirect_route_when_guest',
            'login'
        );

        if (Auth::check()) {
            return redirect()
                ->route($authedRoute)
                ->with($success ? 'status' : 'error', $message);
        }

        return redirect()
            ->route($guestRoute)
            ->with($success ? 'status' : 'error', $message);
    }
}
