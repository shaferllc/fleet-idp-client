@php($layout = config('fleet_idp.email_sign_in.profile_confirm.interstitial_layout', 'fleet-idp::layouts.minimal'))
@extends($layout)

@section('title', $title)

@section('content')
    <div class="space-y-5 text-left">
        <div>
            <h1 class="text-lg font-semibold leading-tight text-ink">
                {{ $title }}
            </h1>
            <p class="mt-2 text-sm leading-relaxed text-ink/70">
                {{ $lead }}
            </p>
            <p class="mt-3 text-xs text-ink/55">
                {{ __('fleet-idp::email_sign_in.profile_confirm_close_tab_hint') }}
            </p>
        </div>

        <form method="post" action="{{ route($routeName) }}" class="space-y-4">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}" autocomplete="off">
            <button
                type="submit"
                class="inline-flex w-full items-center justify-center rounded-lg bg-sage px-4 py-2.5 text-sm font-semibold text-white shadow hover:bg-sage-dark focus:outline-none focus:ring-2 focus:ring-sage focus:ring-offset-2 transition ease-in-out duration-150 sm:w-auto"
            >
                {{ $buttonLabel }}
            </button>
        </form>

        @if (! empty($backToProfileUrl))
            <p class="text-center text-sm text-ink/60 sm:text-left">
                <a href="{{ $backToProfileUrl }}" class="font-medium text-sage hover:text-sage-dark">
                    {{ __('fleet-idp::email_sign_in.profile_confirm_back_to_profile') }}
                </a>
            </p>
        @endif
    </div>
@endsection
