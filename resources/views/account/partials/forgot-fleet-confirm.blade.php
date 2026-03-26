{{--
  Shown when there is no local user but Fleet Auth may have this email, and we could not
  trigger a reset email via the provisioning API (user can open Fleet Auth manually).
  Expects $pending: array with email, url, source, provision_error?, provision_http_status?
--}}
@php($pe = $pending['provision_error'] ?? null)
<div class="fleet-idp-forgot-confirm" role="region" aria-labelledby="fleet-idp-forgot-confirm-title">
    <h1 id="fleet-idp-forgot-confirm-title" style="font-size: 1.25rem; margin-bottom: 0.5rem;">
        {{ trans('fleet-idp::account.fleet_confirm_title_fleet_only') }}
    </h1>

    <p style="color: #444; font-size: 0.875rem; margin-bottom: 1.25rem;" role="status">
        {{ trans('fleet-idp::account.fleet_confirm_fleet_only_detail', ['email' => $pending['email']]) }}
    </p>

    @if (is_string($pe) && $pe !== '')
        <div style="margin-bottom: 1.25rem; padding: 0.75rem 1rem; border-radius: 0.5rem; border: 1px solid #fcd34d; background: rgba(254, 243, 199, 0.5); font-size: 0.875rem; color: #1f2937;" role="note">
            <p style="font-weight: 600; margin: 0 0 0.35rem;">{{ trans('fleet-idp::account.fleet_fallback_provision_hint_title') }}</p>
            <p style="margin: 0;">
                @switch($pe)
                    @case('missing_provisioning_token')
                        {{ trans('fleet-idp::account.fleet_fallback_provision_missing_token') }}
                        @break
                    @case('missing_idp_url')
                        {{ trans('fleet-idp::account.fleet_fallback_provision_missing_url') }}
                        @break
                    @case('unauthorized')
                        {{ trans('fleet-idp::account.fleet_fallback_provision_unauthorized') }}
                        @break
                    @case('service_unavailable')
                        {{ trans('fleet-idp::account.fleet_fallback_provision_unavailable') }}
                        @break
                    @case('exception')
                        <span>{{ trans('fleet-idp::account.fleet_fallback_provision_generic') }}</span>
                        <span style="display: block; margin-top: 0.35rem; font-size: 0.8125rem; color: #4b5563;">{{ trans('fleet-idp::account.fleet_fallback_provision_ssl') }}</span>
                        @break
                    @default
                        @if (is_numeric($pending['provision_http_status'] ?? null))
                            {{ trans('fleet-idp::account.fleet_fallback_provision_http', ['status' => (string) $pending['provision_http_status']]) }}
                        @else
                            {{ trans('fleet-idp::account.fleet_fallback_provision_generic') }}
                        @endif
                @endswitch
            </p>
        </div>
    @endif

    <p style="margin-bottom: 0.75rem;">
        <a href="{{ $pending['url'] }}" style="display: inline-block; padding: 0.5rem 1rem; background: #2563eb; color: #fff; text-decoration: none; border-radius: 0.375rem;">
            {{ trans('fleet-idp::account.fleet_continue_reset') }}
        </a>
    </p>

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
