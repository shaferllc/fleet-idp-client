@php($layout = config('fleet_idp.account.layout', 'fleet-idp::layouts.minimal'))
@extends($layout)

@section('title', trans('fleet-idp::account.reset_title').' — '.config('app.name'))

@section('content')
    <h1 style="font-size: 1.25rem; margin-bottom: 0.5rem;">{{ trans('fleet-idp::account.reset_title') }}</h1>
    <p style="color: #444; font-size: 0.875rem; margin-bottom: 1.25rem;">{{ trans('fleet-idp::account.reset_intro') }}</p>
    <form method="post" action="{{ route(config('fleet_idp.account.route_names.reset_store', 'password.update')) }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <label for="email">{{ trans('fleet-idp::account.email') }}</label>
        <input id="email" type="email" name="email" value="{{ old('email', $email) }}" required autocomplete="username">
        @error('email')
            <p class="error">{{ $message }}</p>
        @enderror
        <label for="password">{{ trans('fleet-idp::account.password') }}</label>
        <input id="password" type="password" name="password" required autocomplete="new-password">
        @error('password')
            <p class="error">{{ $message }}</p>
        @enderror
        <label for="password_confirmation">{{ trans('fleet-idp::account.confirm_password') }}</label>
        <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
        <button type="submit">{{ trans('fleet-idp::account.reset_submit') }}</button>
    </form>
@endsection
