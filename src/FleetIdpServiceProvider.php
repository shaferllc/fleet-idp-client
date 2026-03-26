<?php

namespace Fleet\IdpClient;

use Illuminate\Support\ServiceProvider;

class FleetIdpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/fleet_idp.php', 'fleet_idp');
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'fleet-idp');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/fleet_idp.php' => config_path('fleet_idp.php'),
            ], 'fleet-idp-config');
            $this->publishes([
                __DIR__.'/../lang' => lang_path('vendor/fleet-idp'),
            ], 'fleet-idp-lang');
        }
    }
}
