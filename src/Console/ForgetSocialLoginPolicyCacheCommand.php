<?php

declare(strict_types=1);

namespace Fleet\IdpClient\Console;

use Fleet\IdpClient\Services\FleetSocialLoginPolicy;
use Illuminate\Console\Command;

final class ForgetSocialLoginPolicyCacheCommand extends Command
{
    protected $signature = 'fleet:idp:forget-social-login-policy-cache';

    protected $description = 'Clear the cached Fleet Auth social-login providers policy (2FA / email / OAuth flags)';

    public function handle(): int
    {
        FleetSocialLoginPolicy::forgetCachedSnapshot();
        $this->components->info('Social login policy cache cleared (if any).');

        return self::SUCCESS;
    }
}
