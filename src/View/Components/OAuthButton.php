<?php

namespace Fleet\IdpClient\View\Components;

use Fleet\IdpClient\FleetIdpOAuth;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Component;

class OAuthButton extends Component
{
    /** @var list<string> Nested Livewire views merge a parent attribute bag that can set href="". */
    protected $except = ['href'];

    public function __construct(
        public ?string $href = null,
        public string $variant = 'waypost',
    ) {}

    public function resolvedHref(): string
    {
        if (filled($this->href)) {
            return trim((string) $this->href);
        }

        $name = (string) config('fleet_idp.web.route_names.redirect', 'fleet-idp.oauth.redirect');

        if (! Route::has($name)) {
            return '';
        }

        // Absolute URL avoids wrong host when the browser URL differs from APP_URL (session + redirect_uri must align).
        return route($name, absolute: true);
    }

    public function shouldRender(): bool
    {
        return FleetIdpOAuth::isConfigured() && $this->resolvedHref() !== '';
    }

    public function render(): View
    {
        $slug = strtolower(trim($this->variant));
        $slug = preg_replace('/[^a-z0-9_-]/', '', $slug) ?: 'waypost';
        $candidate = 'fleet-idp::oauth-button.'.$slug;

        $view = view()->exists($candidate) ? $candidate : 'fleet-idp::oauth-button.waypost';

        return view($view, ['href' => $this->resolvedHref()]);
    }
}
