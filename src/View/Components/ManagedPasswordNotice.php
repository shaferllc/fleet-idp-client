<?php

declare(strict_types=1);

namespace Fleet\IdpClient\View\Components;

use Fleet\IdpClient\FleetIdp;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Profile / settings: copy + link when the signed-in user’s password is managed on Fleet Auth.
 */
final class ManagedPasswordNotice extends Component
{
    public function __construct(
        public string $buttonClass = 'inline-flex items-center rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-gray-800',
    ) {}

    public function shouldRender(): bool
    {
        return FleetIdp::passwordManagedByIdp(auth()->user());
    }

    public function render(): View
    {
        return view('fleet-idp::components.managed-password-notice', [
            'changeRoute' => FleetIdp::changePasswordRouteName(),
        ]);
    }
}
