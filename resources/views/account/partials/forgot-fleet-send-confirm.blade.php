{{--
  After the user enters an email that maps to Fleet Auth; they must confirm before we call provisioning.
  Expects $confirm: array{email: string, source: 'linked'|'fleet_only', prompt?: 'standard'|'likely_domain'}
--}}
@php
    $isLinked = ($confirm['source'] ?? '') === 'linked';
    $isLikelyDomain = ($confirm['prompt'] ?? 'standard') === 'likely_domain';
@endphp

<div class="fleet-idp-forgot-fleet-send-confirm" role="region" aria-labelledby="fleet-idp-fleet-send-confirm-title">
    <p style="font-size: 0.875rem; font-weight: 600; color: #1e3a2f; margin-bottom: 0.25rem;" role="status">
        {{ $isLikelyDomain
            ? trans('fleet-idp::account.fleet_reset_confirm_likely_notice')
            : trans('fleet-idp::account.fleet_reset_confirm_address_notice') }}
    </p>

    <h1 id="fleet-idp-fleet-send-confirm-title" style="font-size: 1.25rem; margin-bottom: 0.75rem;">
        {{ $isLikelyDomain
            ? trans('fleet-idp::account.fleet_reset_confirm_likely_title')
            : trans('fleet-idp::account.fleet_reset_confirm_title') }}
    </h1>

    <p style="color: #444; font-size: 0.875rem; margin-bottom: 1.25rem;">
        @if ($isLikelyDomain)
            {{ $isLinked
                ? trans('fleet-idp::account.fleet_reset_confirm_likely_detail_linked', ['email' => $confirm['email']])
                : trans('fleet-idp::account.fleet_reset_confirm_likely_detail_fleet_only', ['email' => $confirm['email']]) }}
        @else
            {{ $isLinked
                ? trans('fleet-idp::account.fleet_reset_confirm_linked_detail', ['email' => $confirm['email']])
                : trans('fleet-idp::account.fleet_reset_confirm_fleet_only_detail', ['email' => $confirm['email']]) }}
        @endif
    </p>

    <form method="post" action="{{ route(config('fleet_idp.account.route_names.forgot_fleet_send', 'password.email.fleet')) }}" style="margin-bottom: 0.75rem;">
        @csrf
        <button type="submit" style="display: inline-block; padding: 0.5rem 1rem; background: #2563eb; color: #fff; border: none; border-radius: 0.375rem; cursor: pointer; font-size: 0.875rem;">
            {{ trans('fleet-idp::account.fleet_reset_confirm_send') }}
        </button>
    </form>

    <p style="font-size: 0.875rem; margin-bottom: 0.5rem;">
        <a href="{{ route(config('fleet_idp.account.route_names.forgot_request', 'password.request'), ['restart' => 1]) }}">
            {{ trans('fleet-idp::account.fleet_try_different_email') }}
        </a>
    </p>

    @if (Route::has('login'))
        <p style="font-size: 0.875rem;">
            <a href="{{ route('login') }}">{{ trans('fleet-idp::account.back_login') }}</a>
        </p>
    @endif
</div>
