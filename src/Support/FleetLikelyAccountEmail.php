<?php

declare(strict_types=1);

namespace Fleet\IdpClient\Support;

/**
 * When an address’s domain is listed in {@see config('fleet_idp.account.likely_email_domains')},
 * forgot-password shows the Fleet confirmation step even if the user row is not Fleet-linked
 * or provisioning lookup does not find the email.
 */
final class FleetLikelyAccountEmail
{
    public static function emailLooksLikeFleetAccount(string $email): bool
    {
        $domains = config('fleet_idp.account.likely_email_domains');
        if (! is_array($domains) || $domains === []) {
            return false;
        }

        $email = strtolower(trim($email));
        $at = strrpos($email, '@');
        if ($at === false) {
            return false;
        }

        $domain = substr($email, $at + 1);
        $domains = array_map(static fn (string $d): string => strtolower(trim($d)), $domains);

        return in_array($domain, $domains, true);
    }
}
