<?php

declare(strict_types=1);

namespace Fleet\IdpClient\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatch from your registrar after creating the local user row, with the plain
 * password, so {@see \Fleet\IdpClient\Listeners\ProvisionRegisteredUserOnFleetAuth}
 * can mirror the account to Fleet Auth. Laravel's {@see \Illuminate\Auth\Events\Registered}
 * does not carry the password — fire both events if you use the default Registered flow.
 */
class UserRegisteredForFleetProvisioning
{
    use Dispatchable;

    public function __construct(
        public Model $user,
        public string $plainPassword,
    ) {}
}
