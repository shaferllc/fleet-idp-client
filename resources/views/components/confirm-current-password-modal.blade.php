{{-- Parent Livewire component must implement confirm + cancel wire methods and (usually) current_password. --}}
<div
    class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6"
    role="dialog"
    aria-modal="true"
    aria-labelledby="{{ $passwordInputId }}-title"
    wire:keydown.escape.window="{{ $cancelWireMethod }}"
>
    <div
        class="absolute inset-0 bg-black/50 dark:bg-black/60"
        wire:click="{{ $cancelWireMethod }}"
        aria-hidden="true"
    ></div>

    <div
        class="relative z-10 w-full max-w-md rounded-lg border border-gray-200 bg-white p-6 shadow-xl dark:border-gray-700 dark:bg-gray-900"
        wire:click.stop
        wire:key="fleet-idp-confirm-password-modal-{{ $passwordInputId }}"
    >
        <h3 id="{{ $passwordInputId }}-title" class="text-lg font-semibold text-gray-900 dark:text-gray-100">
            {{ $title }}
        </h3>

        @if (filled($description))
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                {{ $description }}
            </p>
        @endif

        <form class="mt-4 space-y-4" wire:submit.prevent="{{ $confirmWireMethod }}">
            @if ($requirePassword)
                <div>
                    <label for="{{ $passwordInputId }}" class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                        {{ $passwordLabel ?? __('fleet-idp::email_sign_in.confirm_password_modal_password_label') }}
                    </label>
                    <input
                        id="{{ $passwordInputId }}"
                        type="password"
                        autocomplete="current-password"
                        class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-gray-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                        wire:model="{{ $passwordWireModel }}"
                    />
                    @if ($errors->has($passwordWireModel))
                        <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $errors->first($passwordWireModel) }}</p>
                    @endif
                </div>
            @endif

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button
                    type="button"
                    class="inline-flex justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                    wire:click="{{ $cancelWireMethod }}"
                >
                    {{ $cancelButtonLabel ?? __('fleet-idp::email_sign_in.confirm_password_modal_cancel') }}
                </button>
                <button
                    type="submit"
                    class="inline-flex justify-center rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600"
                >
                    {{ $confirmButtonLabel ?? __('fleet-idp::email_sign_in.confirm_password_modal_confirm') }}
                </button>
            </div>
        </form>
    </div>
</div>
