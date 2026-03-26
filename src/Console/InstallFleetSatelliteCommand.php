<?php

declare(strict_types=1);

namespace Fleet\IdpClient\Console;

use Illuminate\Console\Command;

class InstallFleetSatelliteCommand extends Command
{
    protected $signature = 'fleet:idp:install
                            {--force : Overwrite files that already exist}
                            {--with-config : Also publish config/fleet_idp.php}
                            {--with-migrations : Publish email-sign-in migrations (then set FLEET_IDP_EMAIL_SIGN_IN_LOAD_MIGRATIONS=false in .env)}
                            {--no-views : Skip Blade views (resources/views/vendor/fleet-idp)}
                            {--no-lang : Skip translations (lang/vendor/fleet-idp)}
                            {--no-account-layout : Skip layouts/fleet-idp-account.blade.php stub}';

    protected $description = 'Publish Fleet IdP client Blade views, translations, and optional config/migrations for a satellite app';

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $granular = (bool) $this->option('no-views')
            || (bool) $this->option('no-lang')
            || (bool) $this->option('no-account-layout');

        if ($granular) {
            if (! $this->option('no-views') && $this->publishTag('fleet-idp-views', $force) !== self::SUCCESS) {
                return self::FAILURE;
            }
            if (! $this->option('no-lang') && $this->publishTag('fleet-idp-lang', $force) !== self::SUCCESS) {
                return self::FAILURE;
            }
            if (! $this->option('no-account-layout') && $this->publishTag('fleet-idp-account-layout', $force) !== self::SUCCESS) {
                return self::FAILURE;
            }
        } elseif ($this->publishTag('fleet-idp-satellite', $force) !== self::SUCCESS) {
            return self::FAILURE;
        }

        if ($this->option('with-config') && $this->publishTag('fleet-idp-config', $force) !== self::SUCCESS) {
            return self::FAILURE;
        }

        if ($this->option('with-migrations')) {
            if ($this->publishTag('fleet-idp-email-sign-in-migrations', $force) !== self::SUCCESS) {
                return self::FAILURE;
            }
            $this->newLine();
            $this->warn('Published migration files into database/migrations. Add FLEET_IDP_EMAIL_SIGN_IN_LOAD_MIGRATIONS=false to .env so the package does not auto-load the same migrations twice.');
        }

        $this->newLine();
        $this->info('Fleet IdP satellite scaffolding is ready.');
        $this->line('');
        $this->line('  <fg=cyan>Configure Fleet Auth</>   php artisan fleet:idp:configure --url=… --token=… --name="Your App"');
        $this->line('  <fg=cyan>Theme the auth UI</>       Edit files under resources/views/vendor/fleet-idp/ (see docs/wiki/Publishing-views-and-styling.md)');
        $this->line('  <fg=cyan>AI / assistant context</>  docs/wiki/AI-assistant-satellite-integration.md in this package');

        return self::SUCCESS;
    }

    private function publishTag(string $tag, bool $force): int
    {
        return (int) $this->call('vendor:publish', [
            '--tag' => $tag,
            '--force' => $force,
        ]);
    }
}
