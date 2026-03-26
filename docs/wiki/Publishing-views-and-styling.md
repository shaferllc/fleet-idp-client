# Publishing views and styling

The package ships **working defaults** for OAuth buttons, failure pages, **forgot / reset / change password**, and optional Socialite buttons. Those defaults use a **minimal layout** and generic markup so the package stays framework-agnostic.

For any production satellite (Waypost, Beacon, etc.), you should **publish the views (and usually language files)** and **style them like the rest of your app**. Do not rely on the built-in minimal Blade for customer-facing auth screens.

## What to publish

Run from your Laravel app root:

```bash
php artisan vendor:publish --tag=fleet-idp-views
php artisan vendor:publish --tag=fleet-idp-lang
```

Optional:

```bash
php artisan vendor:publish --tag=fleet-idp-config    # only if you want config in config/fleet_idp.php
php artisan vendor:publish --tag=fleet-idp-account-layout   # starter layout; see below
```

| Tag | Copies to | Purpose |
|-----|-----------|---------|
| **`fleet-idp-views`** | `resources/views/vendor/fleet-idp/` | **Required for custom styling.** Prepended to the `fleet-idp` view namespace, so your files override the package. |
| **`fleet-idp-lang`** | `lang/vendor/fleet-idp/` | Override copy without forking PHP; good for tone and locale. |
| **`fleet-idp-config`** | `config/fleet_idp.php` | Full config in repo; optional if `.env` is enough. |
| **`fleet-idp-account-layout`** | `resources/views/layouts/fleet-idp-account.blade.php` | Neutral starter shell; many apps instead point **`FLEET_IDP_ACCOUNT_LAYOUT`** at their existing **`layouts.guest`** (see [Account and password](Account-and-password)). |

## How overrides work

`FleetIdpServiceProvider` registers:

```php
View::prependNamespace('fleet-idp', resource_path('views/vendor/fleet-idp'));
```

So a file at:

`resources/views/vendor/fleet-idp/account/forgot-password.blade.php`

replaces the package’s `fleet-idp::account.forgot-password`.

**Publish once per app** (or after a major package upgrade) and diff upgrades when you bump the package version.

## App-owned views (recommended for Breeze-style apps)

Instead of publishing into `resources/views/vendor/fleet-idp/`, you can keep **first-party** templates under `resources/views/auth/` and point config at them:

- **`FLEET_IDP_ACCOUNT_VIEW_RESET_PASSWORD`** → e.g. `auth.reset-password`
- **`FLEET_IDP_ACCOUNT_VIEW_FORGOT_PASSWORD`** → e.g. `auth.forgot-password`

See **[Custom account views (reset + profile)](Custom-account-views)** for a step-by-step recipe (reset Blade, Volt profile form, `FleetIdp::passwordManagedByIdp`, and Waypost as a reference copy).

## Account / auth views to style first

Priority order for most apps:

1. **`account/forgot-password.blade.php`** — Email form, Fleet confirmation step, session messages.
2. **`account/partials/forgot-fleet-confirm.blade.php`** — Shown when we **confirmed** a Fleet-linked or Fleet-only email; primary CTA to Fleet reset.
3. **`account/reset-password.blade.php`** — Token + new password (local broker).
4. **`account/change-password.blade.php`** — Local change form (Fleet-linked users are redirected to the IdP by the controller).
5. **`components/managed-password-notice.blade.php`** — Profile/settings “password managed on Fleet” block (`<x-fleet-idp::managed-password-notice />`).
6. **`oauth-failure.blade.php`** — OAuth error page (if you use the package route).
7. **`components/oauth-button/*`** and **`components/social-login-buttons.blade.php`** — Match your login card.

Use your existing **input, label, button, and alert** components (e.g. Breeze / Jetty-style `x-input-label`, `x-text-input`, `x-primary-button`) inside the published templates, the same way you style registration and login.

## Layout

Package views extend **`config('fleet_idp.account.layout')`** (default: `fleet-idp::layouts.minimal`).

Recommended patterns:

- **Single marketing shell** — Point **`FLEET_IDP_ACCOUNT_LAYOUT=layouts.guest`** (or set in `AppServiceProvider`) if `layouts/guest` supports both Livewire **`$slot`** and Blade **`@section('content')`** (see [Account and password](Account-and-password)).
- **Dedicated layout** — Keep a `layouts/fleet-idp-account.blade.php` (published stub or custom) and set **`FLEET_IDP_ACCOUNT_LAYOUT`** accordingly.
- **Auto** — If **`FLEET_IDP_ACCOUNT_AUTO_LAYOUT`** is true and `layouts.fleet-idp-account` exists while layout is still the package minimal default, the provider switches to that view automatically.

Always verify **`@vite`** (or your asset pipeline) is included in whichever layout wraps these pages.

## Translations

Published lang lives under **`lang/vendor/fleet-idp/{locale}/`**. Keys for account flows are in **`account.php`** (`fleet-idp::account.*`). Override strings for product voice and legal tone.

## Checklist before launch

- [ ] Published **`fleet-idp-views`** and themed forgot / reset / confirm partial / change-password.
- [ ] Published **`fleet-idp-lang`** or verified defaults are acceptable.
- [ ] **`fleet_idp.account.layout`** points at a layout that matches login/register (header, footer, fonts, CSS).
- [ ] Tested **local** user forgot → email → reset, and **Fleet-linked** user → confirmation → Fleet URL.
- [ ] Profile page uses **`managed-password-notice`** (or equivalent) for Fleet-managed users.

## Related

- [Custom account views (reset + profile)](Custom-account-views) — Breeze/Volt recipes for other satellites.
- [Account and password](Account-and-password) — behaviour and env vars.
- [Configuration reference](Configuration-reference) — `FLEET_IDP_ACCOUNT_*` and related keys.
