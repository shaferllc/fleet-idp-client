<?php

declare(strict_types=1);

namespace Fleet\IdpClient\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Optional Fleet / password-grant hints shown under the login title on satellites.
 */
final class LoginScreenFleetIntro extends Component
{
    public function render(): View
    {
        return view('fleet-idp::components.login-screen-fleet-intro');
    }
}
