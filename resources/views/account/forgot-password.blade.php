@php($layout = config('fleet_idp.account.layout', 'fleet-idp::layouts.minimal'))
@php($pending = session('fleet_idp_pending_fleet_reset'))
@php($fleetSendConfirm = session('fleet_idp_fleet_reset_confirm'))
@php($showFleetFallback = is_array($pending) && ! empty($pending['url']) && ! empty($pending['email']) && (string) ($pending['source'] ?? '') === 'fleet_only')
@php($showFleetSendConfirm = is_array($fleetSendConfirm) && ! empty($fleetSendConfirm['email']) && in_array((string) ($fleetSendConfirm['source'] ?? ''), ['linked', 'fleet_only'], true))
@extends($layout)

@section('title', trans('fleet-idp::account.forgot_title').' — '.config('app.name'))

@section('content')
    @if ($showFleetFallback)
        @include('fleet-idp::account.partials.forgot-fleet-confirm', ['pending' => $pending])
    @elseif ($showFleetSendConfirm)
        @include('fleet-idp::account.partials.forgot-fleet-send-confirm', ['confirm' => $fleetSendConfirm])
    @else
        <h1 style="font-size: 1.25rem; margin-bottom: 0.5rem;">{{ trans('fleet-idp::account.forgot_title') }}</h1>
        @unless (session('status'))
            <p style="color: #444; font-size: 0.875rem; margin-bottom: 1.25rem;">{{ trans('fleet-idp::account.forgot_intro') }}</p>
        @endunless
        @if (session('status'))
            <p class="status" role="status">{{ session('status') }}</p>
        @endif
        <form method="post" action="{{ route(config('fleet_idp.account.route_names.forgot_store', 'password.email')) }}">
            @csrf
            <label for="email">{{ trans('fleet-idp::account.email') }}</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
            @error('email')
                <p class="error">{{ $message }}</p>
            @enderror
            <button type="submit">{{ trans('fleet-idp::account.send_link') }}</button>
        </form>
        @if (Route::has('login'))
            <p style="margin-top: 1.5rem; font-size: 0.875rem;"><a href="{{ route('login') }}">{{ trans('fleet-idp::account.back_login') }}</a></p>
        @endif
    @endif
@endsection
