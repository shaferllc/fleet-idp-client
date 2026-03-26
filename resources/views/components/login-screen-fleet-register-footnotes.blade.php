@if (Route::has('register'))
    @if (filled(config('fleet_idp.provisioning.token')))
        <p {{ $attributes->class(['mt-2 text-center text-xs text-ink/50']) }}>
            {{ trans('fleet-idp::login.register_help_auto_fleet') }}
        </p>
    @elseif (\Fleet\IdpClient\FleetIdpPasswordGrant::isConfigured() || \Fleet\IdpClient\View\Components\SocialLoginButtons::isEnabled())
        <p {{ $attributes->class(['mt-2 text-center text-xs text-ink/50']) }}>
            {{ trans('fleet-idp::login.register_help_fleet_first') }}
        </p>
    @endif
@endif
