<?php

declare(strict_types=1);

namespace Fleet\IdpClient\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Livewire-friendly modal: confirm an action by re-entering the account password (or confirm only when the user has no password).
 *
 * Render while your Livewire property is true (e.g. @if ($confirmDisableCode) … @endif). Wire methods run on the parent Livewire component.
 */
final class ConfirmCurrentPasswordModal extends Component
{
    public function __construct(
        public string $title,
        public string $confirmWireMethod,
        public string $cancelWireMethod,
        public ?string $description = null,
        public string $passwordWireModel = 'current_password',
        public bool $requirePassword = true,
        public string $passwordInputId = 'fleet_idp_confirm_current_password',
        public ?string $confirmButtonLabel = null,
        public ?string $cancelButtonLabel = null,
        public ?string $passwordLabel = null,
    ) {}

    public function render(): View
    {
        return view('fleet-idp::components.confirm-current-password-modal');
    }
}
