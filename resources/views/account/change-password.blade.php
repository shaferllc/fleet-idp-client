@php($layout = config('fleet_idp.account.layout', 'fleet-idp::layouts.minimal'))
@extends($layout)

@section('title', trans('fleet-idp::account.change_title').' — '.config('app.name'))

@section('content')
    <h1 style="font-size: 1.25rem; margin-bottom: 0.5rem;">{{ trans('fleet-idp::account.change_title') }}</h1>
    <p style="color: #444; font-size: 0.875rem; margin-bottom: 1.25rem;">{{ trans('fleet-idp::account.change_intro') }}</p>
    @if (session('status'))
        <p class="status" role="status">{{ session('status') }}</p>
    @endif
    <form method="post" action="{{ route(config('fleet_idp.account.route_names.change_update', 'fleet-idp.account.password.update')) }}">
        @csrf
        @method('PUT')
        <label for="current_password">{{ trans('fleet-idp::account.current_password') }}</label>
        <input id="current_password" type="password" name="current_password" required autocomplete="current-password">
        @error('current_password')
            <p class="error">{{ $message }}</p>
        @enderror
        <label for="password">{{ trans('fleet-idp::account.new_password') }}</label>
        <input id="password" type="password" name="password" required autocomplete="new-password">
        @error('password')
            <p class="error">{{ $message }}</p>
        @enderror
        <label for="password_confirmation">{{ trans('fleet-idp::account.confirm_password') }}</label>
        <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
        <button type="submit">{{ trans('fleet-idp::account.save') }}</button>
    </form>
@endsection
