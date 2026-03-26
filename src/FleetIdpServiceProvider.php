<?php

namespace Fleet\IdpClient;

use Fleet\IdpClient\Console\ConfigureFleetIdpCommand;
use Fleet\IdpClient\Console\ForgetSocialLoginPolicyCacheCommand;
use Fleet\IdpClient\Console\InstallFleetSatelliteCommand;
use Fleet\IdpClient\Contracts\EmailSignInSessionCompleter;
use Fleet\IdpClient\Http\Middleware\WarmFleetSocialLoginPolicy;
use Fleet\IdpClient\Listeners\ProvisionRegisteredUserOnFleetAuth;
use Fleet\IdpClient\Support\DefaultEmailSignInSessionCompleter;
use Fleet\IdpClient\View\Components\ManagedPasswordNotice;
use Fleet\IdpClient\View\Components\OAuthButton;
use Illuminate\Auth\Events\Registered;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class FleetIdpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/fleet_idp.php', 'fleet_idp');

        $this->app->singleton(EmailSignInSessionCompleter::class, DefaultEmailSignInSessionCompleter::class);
    }

    public function boot(): void
    {
        FleetIdpCustomization::apply($this->app->make('config'));

        $this->normalizeRedirectUri();

        $this->registerWarmSocialLoginPolicyMiddleware();

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
        Blade::component(ManagedPasswordNotice::class, 'fleet-idp::managed-password-notice');

        $this->applyOptionalSatelliteAccountLayout();

        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/socialite.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/account.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/email-sign-in.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/profile-email-sign-in-confirm.php');

        if (filter_var(config('fleet_idp.email_sign_in.load_migrations', true), FILTER_VALIDATE_BOOL)) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                ConfigureFleetIdpCommand::class,
                ForgetSocialLoginPolicyCacheCommand::class,
                InstallFleetSatelliteCommand::class,
            ]);
            $this->publishes([
                __DIR__.'/../config/fleet_idp.php' => config_path('fleet_idp.php'),
                __DIR__.'/../resources/stubs/fleet_idp_overrides.php' => config_path('fleet_idp_overrides.php'),
            ], 'fleet-idp-config');
            $this->publishes([
                __DIR__.'/../resources/stubs/fleet_idp_overrides.php' => config_path('fleet_idp_overrides.php'),
            ], 'fleet-idp-overrides');
            $this->publishes([
                __DIR__.'/../lang' => lang_path('vendor/fleet-idp'),
            ], 'fleet-idp-lang');
            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/fleet-idp'),
            ], 'fleet-idp-views');
            $this->publishes([
                __DIR__.'/../resources/stubs/fleet-idp-account-layout.blade.php' => resource_path('views/layouts/fleet-idp-account.blade.php'),
            ], 'fleet-idp-account-layout');
            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'fleet-idp-email-sign-in-migrations');

            /*
             * One-shot bundle for new satellites: themed auth/account surfaces without config
             * (env-driven defaults stay in the package until --with-config on fleet:idp:install).
             */
            $this->publishes([
                __DIR__.'/../lang' => lang_path('vendor/fleet-idp'),
                __DIR__.'/../resources/views' => resource_path('views/vendor/fleet-idp'),
                __DIR__.'/../resources/stubs/fleet-idp-account-layout.blade.php' => resource_path('views/layouts/fleet-idp-account.blade.php'),
            ], 'fleet-idp-satellite');
        }
    }

    /**
     * If the app ships layouts/fleet-idp-account.blade.php, use it for package account views
     * unless the layout was customized via env.
     */
    protected function applyOptionalSatelliteAccountLayout(): void
    {
        if (! filter_var(config('fleet_idp.account.auto_layout', true), FILTER_VALIDATE_BOOL)) {
            return;
        }

        $minimal = 'fleet-idp::layouts.minimal';
        if ((string) config('fleet_idp.account.layout') !== $minimal) {
            return;
        }

        if (! $this->app->make('view')->exists('layouts.fleet-idp-account')) {
            return;
        }

        $this->app->make('config')->set('fleet_idp.account.layout', 'layouts.fleet-idp-account');
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

    /**
     * Run {@see FleetSocialLoginPolicy::snapshot()} early on each web request (web middleware group).
     */
    protected function registerWarmSocialLoginPolicyMiddleware(): void
    {
        $this->app->booted(function (): void {
            $this->app->make(Router::class)->prependMiddlewareToGroup('web', WarmFleetSocialLoginPolicy::class);
        });
    }
}

