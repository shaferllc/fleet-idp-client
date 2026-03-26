<?php

declare(strict_types=1);

namespace Fleet\IdpClient\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Small-print hints under the register link when Fleet provisioning or OAuth/password grant applies.
 */
final class LoginScreenFleetRegisterFootnotes extends Component
{
    public function render(): View
    {
        return view('fleet-idp::components.login-screen-fleet-register-footnotes');
    }
}
