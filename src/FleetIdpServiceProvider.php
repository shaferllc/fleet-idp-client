<?php

namespace Fleet\IdpClient;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class FleetIdpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/fleet_idp.php', 'fleet_idp');
    }

    public function boot(): void
    {
        $this->normalizeRedirectUri();

        $this->loadTranslationsFrom(__DIR__.'/../lang', 'fleet-idp');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'fleet-idp');

        $publishedViews = resource_path('views/vendor/fleet-idp');
        if (is_dir($publishedViews)) {
            View::prependNamespace('fleet-idp', $publishedViews);
        }

        Blade::componentNamespace('Fleet\\IdpClient\\View\\Components', 'fleet-idp');

        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/fleet_idp.php' => config_path('fleet_idp.php'),
            ], 'fleet-idp-config');
            $this->publishes([
                __DIR__.'/../lang' => lang_path('vendor/fleet-idp'),
            ], 'fleet-idp-lang');
            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/fleet-idp'),
            ], 'fleet-idp-views');
        }
    }

    /**
     * When FLEET_IDP_REDIRECT_URI is omitted, build redirect_uri from config('app.url')
     * and redirect_path so it tracks APP_URL (avoids localhost vs *.test mismatches).
     */
    protected function normalizeRedirectUri(): void
    {
        $config = $this->app->make('config');

        $explicit = $config->get('fleet_idp.redirect_uri');
        if (is_string($explicit) && trim($explicit) !== '') {
            $config->set('fleet_idp.redirect_uri', rtrim(trim($explicit), '/'));

            return;
        }

        $path = (string) $config->get('fleet_idp.redirect_path', '/oauth/fleet-auth/callback');
        $path = '/'.ltrim(trim($path), '/');
        $base = rtrim((string) $config->get('app.url'), '/');

        $config->set('fleet_idp.redirect_uri', $base.$path);
    }
}
