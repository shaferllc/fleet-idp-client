<?php

declare(strict_types=1);

namespace Fleet\IdpClient\Contracts;

use Illuminate\Database\Eloquent\Model;

interface EmailSignInSessionCompleter
{
    /**
     * Start a web session after email code verification or magic link (local or Fleet).
     * Hand off to two-factor challenge when the user model supports it and 2FA is enabled.
     *
     * @return array{mode: 'two_factor'|'session', url: string}
     */
    public function complete(Model $user, bool $remember): array;
}
