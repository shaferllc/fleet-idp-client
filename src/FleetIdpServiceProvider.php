<?php

namespace Fleet\IdpClient;

use Fleet\IdpClient\Console\ConfigureFleetIdpCommand;
use Fleet\IdpClient\Listeners\ProvisionRegisteredUserOnFleetAuth;
use Fleet\IdpClient\View\Components\OAuthButton;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
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

        Event::listen(Registered::class, ProvisionRegisteredUserOnFleetAuth::class);

        $this->loadTranslationsFrom(__DIR__.'/../lang', 'fleet-idp');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'fleet-idp');

        $publishedViews = resource_path('views/vendor/fleet-idp');
        if (is_dir($publishedViews)) {
            View::prependNamespace('fleet-idp', $publishedViews);
        }

        Blade::componentNamespace('Fleet\\IdpClient\\View\\Components', 'fleet-idp');

        /*
         * Laravel maps tag oauth-button to class …\OauthButton (see ComponentTagCompiler::formatClassName).
         * Our class is OAuthButton; PSR-4 file names differ on case-sensitive disks, so register explicitly.
         */
        Blade::component(OAuthButton::class, 'fleet-idp::oauth-button');

        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/socialite.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ConfigureFleetIdpCommand::class,
            ]);
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
     * Normalize an explicit FLEET_IDP_REDIRECT_URI. When it is omitted, leave
     * fleet_idp.redirect_uri empty so {@see FleetIdpOAuth::redirectUri()} can derive
     * the callback from the current HTTP request (avoids localhost vs *.test).
     */
    protected function normalizeRedirectUri(): void
    {
        $config = $this->app->make('config');

        $explicit = $config->get('fleet_idp.redirect_uri');
        if (is_string($explicit) && trim($explicit) !== '') {
            $config->set('fleet_idp.redirect_uri', rtrim(trim($explicit), '/'));

            return;
        }

        $config->set('fleet_idp.redirect_uri', null);
    }
}
