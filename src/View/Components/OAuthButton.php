<?php

namespace Fleet\IdpClient\View\Components;

use Fleet\IdpClient\FleetIdpOAuth;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class OAuthButton extends Component
{
    public function __construct(
        public string $href,
        public string $variant = 'waypost',
    ) {}

    public function shouldRender(): bool
    {
        return FleetIdpOAuth::isConfigured() && trim($this->href) !== '';
    }

    public function render(): View
    {
        $slug = strtolower(trim($this->variant));
        $slug = preg_replace('/[^a-z0-9_-]/', '', $slug) ?: 'waypost';
        $candidate = 'fleet-idp::oauth-button.'.$slug;

        $view = view()->exists($candidate) ? $candidate : 'fleet-idp::oauth-button.waypost';

        return view($view);
    }
}
