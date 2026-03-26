# fleet/idp-client

Laravel package: OAuth2 **authorization-code** flow + optional Passport **password grant** against **Fleet Auth**, plus Eloquent user provisioning from the IdP `GET /api/user` response.

**Private registry (install):** [packages.shafer.llc — fleet/idp-client](https://packages.shafer.llc/packages/fleet/idp-client)

**Source:** [github.com/shaferllc/fleet-idp-client](https://github.com/shaferllc/fleet-idp-client)

**Identity provider:** [github.com/shaferllc/fleet-auth](https://github.com/shaferllc/fleet-auth)

## Requirements

- PHP **8.3+**
- Laravel **12** or **13** (Illuminate `^12.0|^13.0`)

## Install from packages.shafer.llc

The package is published on our **Packeton** Composer mirror. Add the repository root (not the per-package URL), then require the package:

```json
"repositories": [
    {
        "type": "composer",
        "url": "https://packages.shafer.llc"
    }
],
"require": {
    "fleet/idp-client": "^0.1"
}
```

Authenticate with the registry (your Packeton username/API token or password — see your account on [packages.shafer.llc](https://packages.shafer.llc)):

```bash
composer config --global http-basic.packages.shafer.llc YOUR_USERNAME YOUR_TOKEN
# or project-local: composer config http-basic.packages.shafer.llc YOUR_USERNAME YOUR_TOKEN
```

Then:

```bash
composer update fleet/idp-client
```

The service provider is **auto-discovered**; no manual `config/app.php` entry.

### Monorepo / local path (developers)

If you keep this repo next to an app, add a **path** repository **after** the Composer repo and mark the registry as **non-canonical** so Composer can satisfy `^0.1` from the path checkout when the mirror only exposes `dev-main` (see [repository priority](https://getcomposer.org/repoprio)):

```json
"repositories": [
    {
        "type": "composer",
        "url": "https://packages.shafer.llc",
        "canonical": false
    },
    {
        "type": "path",
        "url": "../fleet-idp-client",
        "options": { "symlink": true }
    }
],
"require": {
    "fleet/idp-client": "^0.1"
}
```

If you **only** install from `packages.shafer.llc` (no path repo), omit `canonical: false` once the registry publishes a **tagged** `0.1.x` release that satisfies `^0.1`.

### GitHub (VCS) fallback

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/shaferllc/fleet-idp-client" }
],
"require": {
    "fleet/idp-client": "^0.1"
}
```

## Configuration

Config is merged from the package (`config/fleet_idp.php`). Override with `.env`:

| Variable | Purpose |
|----------|---------|
| `FLEET_IDP_URL` | Fleet Auth **root** only (e.g. `https://fleet-auth.example.com`). Never your app’s callback URL. |
| `FLEET_IDP_CLIENT_ID` / `FLEET_IDP_CLIENT_SECRET` | Authorization-code OAuth client |
| `FLEET_IDP_REDIRECT_URI` | Full callback URL; if unset, package uses `rtrim(APP_URL) . redirect_path` |
| `FLEET_IDP_REDIRECT_PATH` | Path segment only (default `/oauth/fleet-auth/callback`). Fleet Console typically uses `/auth/callback`. |
| `FLEET_IDP_PASSWORD_CLIENT_ID` / `FLEET_IDP_PASSWORD_CLIENT_SECRET` | Optional password grant |
| `FLEET_IDP_USER_MODEL` | Eloquent user class for provisioning |
| `FLEET_IDP_SESSION_STATE_KEY` | Session key for OAuth `state` (optional) |

Optional publish:

```bash
php artisan vendor:publish --tag=fleet-idp-config
php artisan vendor:publish --tag=fleet-idp-lang
php artisan vendor:publish --tag=fleet-idp-views
```

### OAuth sign-in button (Blade component)

Use **`x-fleet-idp::oauth-button`** for a styled link to your OAuth redirect URL. It renders **nothing** unless `FleetIdpOAuth::isConfigured()` is true.

| Prop | Default | Description |
|------|---------|-------------|
| `href` | (required) | Full URL or `route(...)` to start the OAuth flow |
| `variant` | `waypost` | Which packaged markup/CSS to use |

Built-in variants:

| `variant` | Intended app | Notes |
|-----------|----------------|-------|
| `waypost` | Waypost | Sage/cream Tailwind classes (`bg-sage`, etc.) |
| `console` | Fleet Console | `fc-btn-primary` / glass layout classes |

Unknown `variant` values fall back to `waypost`. Customize or add variants by publishing **`fleet-idp-views`**; files under `resources/views/vendor/fleet-idp/` override the package (same path, e.g. `oauth-button/waypost.blade.php`). You can also open a PR to ship another first-party variant.

```blade
<x-fleet-idp::oauth-button
    :href="route('oauth.fleet-auth.redirect')"
    variant="waypost"
/>

<x-fleet-idp::oauth-button
    :href="route('console.login', ['sso' => '1'])"
    variant="console"
/>
```

Labels use `fleet-idp::oauth.*` translations (`continue_with_fleet`, `sign_in_with_fleet_account`); publish `fleet-idp-lang` to override.

## Programmatic API

- **OAuth:** `Fleet\IdpClient\FleetIdpOAuth` — `isConfigured()`, `redirectUri()`, `authorizationRedirectUrl()`, `exchangeCode()`, `fetchUser()`, `fetchUserWithToken()`, `requireIdpRootUrl()`.
- **Password grant + sync:** `Fleet\IdpClient\FleetIdpPasswordGrant::attempt($email, $password)` → local user or `null`.
- **Sync only:** `Fleet\IdpClient\FleetIdpEloquentUserProvisioner::syncFromRemoteUser($remote)` — expects IdP `/api/user` JSON.

## Views and UI (how to implement in your app)

Ships an optional **`x-fleet-idp::oauth-button`** (see above). You still own **routes**, **controllers**, and any layout beyond that button.

### 1. Routes (guest)

Register a **redirect** route (starts the OAuth flow) and a **callback** route (exchanges `code`, loads user, starts session).

Example paths:

| App | Redirect | Callback |
|-----|----------|----------|
| Waypost | `GET /oauth/fleet-auth` | `GET /oauth/fleet-auth/callback` |
| Fleet Console | `GET /login?sso=1` (or dedicated path) | `GET /auth/callback` |

The callback URL must **exactly** match a redirect URI registered on the Passport client in Fleet Auth.

### 2. Controller pattern

- **Redirect:** if `FleetIdpOAuth::isConfigured()`, `return redirect()->away(FleetIdpOAuth::authorizationRedirectUrl());` (stores `state` in session).
- **Callback:** validate `code` + `state` against `config('fleet_idp.session_oauth_state_key')`, then `FleetIdpOAuth::exchangeCode($code)`, `FleetIdpOAuth::fetchUser($accessToken)`, then either:
  - **Eloquent app (e.g. Waypost):** `FleetIdpEloquentUserProvisioner::syncFromRemoteUser($remote)` and `Auth::login($user)`.
  - **Session-only app (e.g. Fleet Console):** store IdP profile in session and set your app’s “logged in” flag.

Reference implementations:

- Waypost: `App\Http\Controllers\Auth\FleetAuthController`, routes in `routes/auth.php`.
- Fleet Console: `App\Http\Controllers\ConsoleAuthController`, SSO via `?sso=1` on the login page.

### 3. Views / Livewire

- **OAuth button:** use **`x-fleet-idp::oauth-button`** with `:href` and `variant` (see above); it no-ops when the IdP is not configured.
- **Password grant:** in your login action, if `FleetIdpPasswordGrant::isConfigured()`, call `FleetIdpPasswordGrant::attempt($email, $password)` before falling back to local `Auth::attempt`.
- **Copy / layout:** optional hints when IdP is configured (Waypost Volt login checks `FleetIdpPasswordGrant::isConfigured()` and `FleetIdpOAuth::isConfigured()` for helper text).

Waypost reference files:

- `resources/views/components/oauth-providers.blade.php` — “Continue with Fleet” button.
- `app/View/Components/OauthProviders.php` — sets `fleetAuthEnabled` from `FleetIdpOAuth::isConfigured()`.
- `resources/views/livewire/pages/auth/login.blade.php` — password grant + OAuth providers + divider.

Fleet Console reference:

- `resources/views/console/login.blade.php` — “Sign in with Fleet account” when `$fleetIdpEnabled` is true.

### 4. Translations

Validation / error strings from the package live under `fleet-idp::errors.*` if published; you can also use your own messages in the controller when redirecting back to login with `oauth_error` or validation errors.

## License

MIT — see `composer.json`.
