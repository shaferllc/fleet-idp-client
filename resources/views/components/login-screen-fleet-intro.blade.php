<div {{ $attributes }}>
    @if (\Fleet\IdpClient\FleetIdpPasswordGrant::isConfigured())
        <p class="mt-3 text-xs leading-relaxed text-ink/55">
            @if (\Fleet\IdpClient\View\Components\SocialLoginButtons::isEnabled() && \Fleet\IdpClient\FleetIdpOAuth::isConfigured())
                {{ trans('fleet-idp::login.password_grant_with_fleet_sign_in', ['app' => config('app.name')]) }}
            @else
                {{ trans('fleet-idp::login.password_grant', ['app' => config('app.name')]) }}
            @endif
        </p>
    @elseif (\Fleet\IdpClient\FleetIdpOAuth::isConfigured() && \Fleet\IdpClient\View\Components\SocialLoginButtons::isEnabled())
        <p class="mt-3 text-xs leading-relaxed text-ink/55">
            {{ trans('fleet-idp::login.oauth_session_hint', ['app' => config('app.name')]) }}
        </p>
    @endif
</div>
