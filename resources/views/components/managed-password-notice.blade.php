<p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
    {{ trans('fleet-idp::account.profile_managed_intro') }}
</p>
<div class="mt-4">
    <a href="{{ route($changeRoute) }}" class="{{ $buttonClass }}">
        {{ trans('fleet-idp::account.profile_change_on_fleet') }}
    </a>
</div>
