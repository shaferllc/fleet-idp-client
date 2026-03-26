<a
    href="{{ $href }}"
    {{ $attributes->merge(['class' => 'inline-flex w-full items-center justify-center rounded-lg bg-sage px-4 py-2.5 text-sm font-semibold text-white shadow hover:bg-sage-dark focus:outline-none focus:ring-2 focus:ring-sage focus:ring-offset-2 transition ease-in-out duration-150']) }}
>
    {{ trans('fleet-idp::oauth.continue_with_fleet') }}
</a>
