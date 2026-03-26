<div {{ $attributes }}>
    @if ($showSessionAlerts)
        @if (session('oauth_error'))
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800" role="alert">
                {{ session('oauth_error') }}
            </div>
        @endif

        @if (session('error'))
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800" role="alert">
                {{ session('error') }}
            </div>
        @endif
    @endif

    <x-fleet-idp::social-login-buttons class="mb-6" :variant="$variant" />

    @if (\Fleet\IdpClient\View\Components\SocialLoginButtons::isEnabled())
        <div class="relative mb-6">
            <div class="absolute inset-0 flex items-center" aria-hidden="true">
                <div class="w-full border-t border-cream-300"></div>
            </div>
            <div class="relative flex justify-center text-xs uppercase tracking-wide">
                <span class="bg-cream-50 px-3 text-ink/55">{{ trans('fleet-idp::login.or_sign_in_with_email') }}</span>
            </div>
        </div>
    @endif

    @if ($showPasswordlessCard)
        <div class="mb-6 rounded-xl border border-cream-300/90 bg-white/70 p-4 shadow-sm ring-1 ring-ink/5">
            <h2 class="text-sm font-semibold text-ink">{{ trans('fleet-idp::login.sign_in_without_password') }}</h2>
            <p class="mt-1 text-xs leading-relaxed text-ink/60">
                {{ trans('fleet-idp::login.sign_in_without_password_help') }}
            </p>
            <a
                href="{{ route($guestEmailCodeRouteName) }}"
                @if ($wireNavigate) wire:navigate @endif
                class="mt-3 inline-flex w-full items-center justify-center rounded-lg border-2 border-sage/35 bg-white px-4 py-2.5 text-sm font-semibold text-sage-dark shadow-sm hover:border-sage hover:bg-cream-50 focus:outline-none focus:ring-2 focus:ring-sage focus:ring-offset-2 focus:ring-offset-cream-50 transition ease-in-out duration-150"
            >
                {{ trans('fleet-idp::login.continue_email_code_or_link') }}
            </a>
        </div>

        <div class="relative mb-5">
            <div class="absolute inset-0 flex items-center" aria-hidden="true">
                <div class="w-full border-t border-cream-300"></div>
            </div>
            <div class="relative flex justify-center text-xs uppercase tracking-wide">
                <span class="bg-cream-50 px-3 text-ink/55">{{ trans('fleet-idp::login.or_use_password') }}</span>
            </div>
        </div>
    @endif
</div>
