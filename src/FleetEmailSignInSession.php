<?php

declare(strict_types=1);

namespace Fleet\IdpClient;

use Fleet\IdpClient\Contracts\EmailSignInSessionCompleter;
use Illuminate\Database\Eloquent\Model;

/**
 * Completes a browser session after email code or magic link sign-in.
 */
final class FleetEmailSignInSession
{
    /**
     * @return array{mode: 'two_factor'|'session', url: string}
     */
    public static function complete(Model $user, bool $remember): array
    {
        return app(EmailSignInSessionCompleter::class)->complete($user, $remember);
    }
}
