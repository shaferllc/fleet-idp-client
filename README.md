# fleet/idp-client

Laravel package: OAuth2 **authorization-code** flow + optional Passport **password grant** against **Fleet Auth**, Eloquent user provisioning, **registered web routes**, and optional **Blade sign-in button** variants.

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
    "fleet/idp-client": "^0.2"
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

If you keep this repo next to an app, add a **path** repository **after** the Composer repo and mark the registry as **non-canonical** so Composer can satisfy the constraint from the path checkout when the mirror only exposes `dev-main` (see [repository priority](https://getcomposer.org/repoprio)):

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
    "fleet/idp-client": "^0.2"
}
```

If you **only** install from `packages.shafer.llc` (no path repo), omit `canonical: false` once the registry publishes a **tagged** release that satisfies `^0.2`.

### GitHub (VCS) fallback

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/shaferllc/fleet-idp-client" }
],
"require": {
    "fleet/idp-client": "^0.2"
}
```

## Minimal app integration

1. Set **`.env`** (`FLEET_IDP_URL`, client id/secret, redirect path/URI to match Passport — see [Configuration](#configuration)).
2. Choose **`FLEET_IDP_WEB_MODE`**: `eloquent` (default, Breeze-style login + `Auth::login`) or `session` (Fleet Console: session flags + IdP user array).
3. Drop **`x-fleet-idp::oauth-button`** (with `variant`) on your login screen — **`href` is optional**; it defaults to the package redirect route.

The package registers **`GET`** OAuth **start** and **callback** routes (see `routes/web.php`). Set `FLEET_IDP_WEB_ENABLED=false` if you register routes yourself.

**Password grant** (email/password against Fleet Auth) is still a few lines in your login action: call `FleetIdpPasswordGrant::attempt()` before local `Auth::attempt()` (see Waypost’s Volt login).

## Configuration

Config is merged from the package (`config/fleet_idp.php`). Override with `.env` or publish:

```bash
php artisan vendor:publish --tag=fleet-idp-config
php artisan vendor:publish --tag=fleet-idp-lang
php artisan vendor:publish --tag=fleet-idp-views
```

### Core

| Variable | Purpose |
|----------|---------|
| `FLEET_IDP_URL` | Fleet Auth **root** only. Never your app’s callback URL. |
| `FLEET_IDP_CLIENT_ID` / `FLEET_IDP_CLIENT_SECRET` | Authorization-code OAuth client |
| `FLEET_IDP_REDIRECT_URI` | Full callback URL; if unset, package uses `rtrim(APP_URL) + FLEET_IDP_REDIRECT_PATH` |
| `FLEET_IDP_REDIRECT_PATH` | Callback **path** only (default `/oauth/fleet-auth/callback`). Fleet Console: `/auth/callback`. |
| `FLEET_IDP_PASSWORD_*` | Optional password grant |
| `FLEET_IDP_USER_MODEL` | Eloquent user class |
| `FLEET_IDP_SESSION_STATE_KEY` | OAuth `state` session key |

### Web routes & modes

| Variable | Default | Purpose |
|----------|---------|---------|
| `FLEET_IDP_WEB_ENABLED` | `true` | Register package OAuth routes |
| `FLEET_IDP_WEB_MODE` | `eloquent` | `eloquent` or `session` |
| `FLEET_IDP_OAUTH_START_PATH` | `/oauth/fleet-auth` | Browser hits your app here to start OAuth |
| `FLEET_IDP_WEB_MIDDLEWARE` | `web` | Comma-separated middleware (Fleet Console: `web,fleet.trusted_ip`) |
| `FLEET_IDP_ROUTE_OAUTH_REDIRECT` | `fleet-idp.oauth.redirect` | Route name for start |
| `FLEET_IDP_ROUTE_OAUTH_CALLBACK` | `fleet-idp.oauth.callback` | Route name for callback |

**Eloquent mode** (typical Breeze app):

| Variable | Default |
|----------|---------|
| `FLEET_IDP_OAUTH_ERROR_ROUTE` | `login` |
| `FLEET_IDP_OAUTH_ERROR_SESSION_KEY` | `oauth_error` |
| `FLEET_IDP_POST_LOGIN_ROUTE` | `dashboard` |
| `FLEET_IDP_TWO_FACTOR_ROUTE` | `two-factor.challenge` |

**Session mode** (Fleet Console–style):

| Variable | Default |
|----------|---------|
| `FLEET_IDP_SESSION_OAUTH_ERROR_ROUTE` | `console.login` |
| `FLEET_IDP_SESSION_ERROR_KEY` | `password` (validation error bag key) |
| `FLEET_IDP_SESSION_AUTH_KEY` | `fleet_console_ok` |
| `FLEET_IDP_SESSION_USER_KEY` | `fleet_idp_user` |
| `FLEET_IDP_SESSION_POST_LOGIN_ROUTE` | `console.dashboard` |

## OAuth sign-in button (Blade)

**`x-fleet-idp::oauth-button`** links to the OAuth start route (or a custom `href`). Renders nothing if the IdP is not configured or the route is missing.

| Prop | Default | Description |
|------|---------|-------------|
| `href` | package redirect route | Override URL |
| `variant` | `waypost` | `waypost`, `console`, or a published view slug |

```blade
<x-fleet-idp::oauth-button variant="waypost" />
<x-fleet-idp::oauth-button variant="console" />
```

Publish **`fleet-idp-views`** to override templates under `resources/views/vendor/fleet-idp/` (prepended to the view namespace).

## Programmatic API

- **OAuth:** `FleetIdpOAuth` — `isConfigured()`, `redirectUri()`, `authorizationRedirectUrl()`, `exchangeCode()`, `fetchUser()`, …
- **Password grant:** `FleetIdpPasswordGrant::attempt($email, $password)`
- **Sync:** `FleetIdpEloquentUserProvisioner::syncFromRemoteUser($remote)`

## License

MIT — see `composer.json`.
