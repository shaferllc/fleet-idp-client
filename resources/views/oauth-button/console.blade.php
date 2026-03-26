<a
    href="{{ $href }}"
    {{ $attributes->merge(['class' => 'fc-btn-primary flex w-full items-center justify-center rounded-xl px-4 py-3 text-sm font-semibold text-white no-underline']) }}
>
    {{ trans('fleet-idp::oauth.sign_in_with_fleet_account') }}
</a>
