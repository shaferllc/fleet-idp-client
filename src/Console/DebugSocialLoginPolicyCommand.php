<?php

declare(strict_types=1);

namespace Fleet\IdpClient\Console;

use Fleet\IdpClient\Services\FleetSocialLoginPolicy;
use Illuminate\Console\Command;
use Throwable;

final class DebugSocialLoginPolicyCommand extends Command
{
    protected $signature = 'fleet:idp:debug-social-policy {--json : Output raw JSON only}';

    protected $description = 'Show Fleet Auth social-login providers diagnostics (env, HTTP probe, resolved policy flags)';

    public function handle(): int
    {
        try {
            $diag = FleetSocialLoginPolicy::diagnostics();
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($diag, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->components->info('Fleet IdP social-login policy diagnostics');
        $this->newLine();

        $this->components->twoColumnDetail('APP_ENV', (string) ($diag['app_env'] ?? ''));
        $this->components->twoColumnDetail('FLEET_IDP_URL (base)', $diag['fleet_idp_base_url'] ?? '(empty)');
        $this->components->twoColumnDetail('OAuth client id set', ($diag['client_id_set'] ?? false) ? 'yes' : 'no');
        $this->components->twoColumnDetail('OAuth client secret set', ($diag['client_secret_set'] ?? false) ? 'yes' : 'no');
        $this->components->twoColumnDetail('FleetIdpOAuth::isConfigured()', ($diag['oauth_configured'] ?? false) ? 'yes' : 'no');
        $this->components->twoColumnDetail('Password grant configured', ($diag['password_grant_configured'] ?? false) ? 'yes' : 'no');
        $this->components->twoColumnDetail('Verify TLS (provisioning flag)', ($diag['verify_tls'] ?? false) ? 'yes' : 'no');
        $this->components->twoColumnDetail('FLEET_IDP_OPTIMISTIC_UNREACHABLE', ($diag['optimistic_when_unreachable'] ?? false) ? 'true' : 'false');
        $this->components->twoColumnDetail('Policy cache TTL (seconds)', (string) ($diag['policy_cache_seconds'] ?? ''));
        $this->components->twoColumnDetail('HTTP timeout (seconds)', (string) ($diag['policy_timeout_seconds'] ?? ''));
        $this->components->twoColumnDetail('FLEET_IDP_DEBUG_SOCIAL_POLICY', ($diag['debug_policy_fetch_enabled'] ?? false) ? 'true' : 'false');
        $this->components->twoColumnDetail('FleetSocialLoginPolicy::fake()', ($diag['using_fake_override'] ?? false) ? 'active' : 'no');
        $this->newLine();

        $this->components->twoColumnDetail('Providers URL', $diag['providers_request_url'] ?? '(n/a)');
        $this->components->twoColumnDetail('Policy cache key', $diag['policy_cache_key'] ?? '(n/a)');
        $this->newLine();

        $this->components->info('Resolved snapshot (what the app uses right now)');
        foreach ($diag['resolved_snapshot'] ?? [] as $flag => $value) {
            $this->components->twoColumnDetail((string) $flag, $value ? 'true' : 'false');
        }
        $this->newLine();

        $attempts = $diag['http_probe_attempts'] ?? [];
        if ($attempts === []) {
            $this->components->warn('No HTTP probe (FLEET_IDP_URL empty).');
        } else {
            $this->components->info('Fresh HTTP probe (independent of cache; may duplicate a request already made for snapshot())');
            foreach ($attempts as $i => $row) {
                $n = (int) $i + 1;
                $this->components->twoColumnDetail("Attempt {$n} URL", (string) ($row['url'] ?? ''));
                $this->components->twoColumnDetail("Attempt {$n} status", isset($row['status']) ? (string) $row['status'] : '(exception)');
                $this->components->twoColumnDetail("Attempt {$n} ok", ! empty($row['ok']) ? 'yes' : 'no');
                if (! empty($row['exception'])) {
                    $this->components->twoColumnDetail("Attempt {$n} exception", (string) $row['exception']);
                }
                if (isset($row['json_top_level_keys']) && is_array($row['json_top_level_keys'])) {
                    $this->components->twoColumnDetail("Attempt {$n} JSON keys", implode(', ', $row['json_top_level_keys']));
                }
                if (! empty($row['json_message'])) {
                    $this->components->twoColumnDetail("Attempt {$n} message", (string) $row['json_message']);
                }
                $this->newLine();
            }
        }

        $this->components->info('Tip: set FLEET_IDP_DEBUG_SOCIAL_POLICY=true and tail storage/logs/laravel.log for per-request policy logs.');

        return self::SUCCESS;
    }
}
