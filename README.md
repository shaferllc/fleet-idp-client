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
    "fleet/idp-client": "^0.4"
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

### Deploy follow-up (staging / production)

Treat **[packages.shafer.llc — `fleet/idp-client`](https://packages.shafer.llc/packages/fleet/idp-client)** as the **source of truth** for installs on servers and in CI: **`composer install` / `composer update`** must reach **`https://packages.shafer.llc`** with valid **`http-basic.packages.shafer.llc`** (or **`COMPOSER_AUTH`**) credentials. **Path** and **VCS** repositories are for local development; a deploy pipeline that only has a path checkout will not install this package in production. After each deploy, confirm the build log resolved **`fleet/idp-client`** from the mirror (not a missing symlinked path).

If installs fail with **`git@github.com: Permission denied (publickey)`**, Composer is using **git source** instead of a **dist** zip. Fix by: making the GitHub repo **public** (Packeton can stay private), configuring **Packeton** to host **dist** archives (with server-side Git credentials), or setting **`COMPOSER_AUTH`** for **github.com** on the deploy host. See your app README (e.g. Waypost **Deploy** troubleshooting) for detail.

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
    "fleet/idp-client": "^0.4"
}
```

If you **only** install from `packages.shafer.llc` (no path repo), omit `canonical: false` once the registry publishes a **tagged** release that satisfies your constraint (e.g. `^0.4`).

### GitHub (VCS) fallback

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/shaferllc/fleet-idp-client" }
],
"require": {
    "fleet/idp-client": "^0.4"
}
```

## Minimal app integration

1. Set **`.env`** — either run **`php artisan fleet:idp:configure`** against Fleet Auth (see [CLI bootstrap](#cli-bootstrap-fleetidpconfigure)) or set `FLEET_IDP_URL`, client id/secret, and redirect path/URI manually (see [Configuration](#configuration)).
2. Choose **`FLEET_IDP_WEB_MODE`**: `eloquent` (default, Breeze-style login + `Auth::login`) or `session` (Fleet Console: session flags + IdP user array).
3. Drop **`x-fleet-idp::oauth-button`** (with `variant`) on your login screen — **`href` is optional**; it defaults to the package redirect route.

The package registers **`GET`** OAuth **start** and **callback** routes (see `routes/web.php`). Set `FLEET_IDP_WEB_ENABLED=false` if you register routes yourself.

**Password grant** (email/password against Fleet Auth) is still a few lines in your login action: call `FleetIdpPasswordGrant::attempt()` before local `Auth::attempt()` (see Waypost’s Volt login).

## CLI bootstrap (`fleet:idp:configure`)

From **0.4.0**, the package registers an Artisan command that calls Fleet Auth’s **`POST /api/cli/setup`** and merges returned credentials into your app’s **`.env`**.

### Fleet Auth (IdP) prerequisites

- Set **`FLEET_AUTH_CLI_SETUP_TOKEN`** on Fleet Auth to a long random secret. If it is empty, the setup route is **not exposed** (clients receive **404**).
- The token is sent as a **Bearer** header from the client app; it is **not** written into the client `.env`.

### Usage (client app)

Run from the **Laravel app root** (where `.env` lives). Use **`--dry-run`** to print JSON without modifying `.env`.

```bash
php artisan fleet:idp:configure \
  --url=https://fleet-auth.example \
  --token="$FLEET_AUTH_CLI_SETUP_TOKEN"
```

Interactive mode: omit `--url` / `--token` and the command will prompt.

| Option | Description |
|--------|---------------|
| `--url=` | Fleet Auth **root** URL (no trailing path). |
| `--token=` | Bearer value matching **`FLEET_AUTH_CLI_SETUP_TOKEN`** on Fleet Auth. |
| `--name=` | Integration label (default `Waypost`). Creates/updates Passport clients named `{name}` and `{name} (password grant)`. |
| `--redirect=` | Repeatable. OAuth **redirect_uri** values to register. Default: **`APP_URL` + `/oauth/fleet-auth/callback`** (requires `APP_URL` or `--client-url`). |
| `--client-url=` | Trusted client **base URL** in Fleet Auth (`client_base_url`). Default: **`APP_URL`**. |
| `--no-rotate` | Only merge redirect URIs; **do not** rotate existing client or provisioning secrets. |
| `--dry-run` | Print the JSON response only; do **not** write `.env`. |
| `--env-file=` | Relative path to env file (default `.env`). |

### Keys written to `.env`

The command updates or appends these variables when Fleet Auth returns a non-empty value:

| Variable | Purpose |
|----------|---------|
| `FLEET_IDP_URL` | IdP root (from response or `--url`). |
| `FLEET_IDP_CLIENT_ID` / `FLEET_IDP_CLIENT_SECRET` | Authorization-code OAuth client |
| `FLEET_IDP_PASSWORD_CLIENT_ID` / `FLEET_IDP_PASSWORD_CLIENT_SECRET` | Password grant client |
| `FLEET_AUTH_PROVISIONING_TOKEN` | Bearer for `POST /api/provisioning/users` (registration mirroring) |

If the API omits a secret (for example **`--no-rotate`** and the IdP keeps an existing hashed secret), that key is **left unchanged** in `.env` — existing values are not cleared.

**`FLEET_AUTH_PROVISIONING_URL`** is not set by the command; leave it unset to use the default **`{FLEET_IDP_URL}/api/provisioning/users`** (see `config/fleet_idp.php` → `provisioning.url`).

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
| `FLEET_IDP_REDIRECT_URI` | Full callback URL; if unset, uses **current request** scheme + host + `FLEET_IDP_REDIRECT_PATH` (falls back to `APP_URL` when no HTTP request, e.g. Artisan) |
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
| `FLEET_IDP_OAUTH_FAILURE_PATH` | `/oauth/fleet-auth/failure` | Dedicated OAuth failure page path |
| `FLEET_IDP_ROUTE_OAUTH_FAILURE` | `fleet-idp.oauth.failure` | Route name for failure page |

**Eloquent mode** (typical Breeze app):

| Variable | Default |
|----------|---------|
| `FLEET_IDP_OAUTH_ERROR_ROUTE` | `fleet-idp.oauth.failure` (package page; set to `login` to flash on login only) |
| `FLEET_IDP_TRY_AGAIN_ROUTE` | `login` | “Back to log in” target on the failure page |
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

**`x-fleet-idp::oauth-button`** links to the OAuth start route (or a custom `href`). Renders nothing if the IdP is not configured or the route is missing. The `href` constructor argument is excluded from Blade `data()` merges so **Livewire/Volt** parent attribute bags cannot overwrite the generated URL with an empty `href`.

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

- **CLI:** `fleet:idp:configure` — HTTP setup against Fleet Auth; see [CLI bootstrap](#cli-bootstrap-fleetidpconfigure).
- **OAuth:** `FleetIdpOAuth` — `isConfigured()`, `redirectUri()`, `authorizationRedirectUrl()`, `exchangeCode()`, `fetchUser()`, …
- **Password grant:** `FleetIdpPasswordGrant::attempt($email, $password)`
- **Sync:** `FleetIdpEloquentUserProvisioner::syncFromRemoteUser($remote)`
- **`.env` merge:** `Fleet\IdpClient\Support\EnvFileWriter::mergeIntoFile()` (used by the configure command)

## License

MIT — see `composer.json`.
