# Fleet IdP client for Laravel

Composer package **`shaferllc/fleet-idp-client`** (PHP namespace `Fleet\IdpClient\…`). OAuth2 **authorization-code** flow + optional Passport **password grant** against **Fleet Auth**, Eloquent user provisioning, **registered web routes**, and optional **Blade sign-in button** variants.

**Packagist:** [packagist.org/packages/shaferllc/fleet-idp-client](https://packagist.org/packages/shaferllc/fleet-idp-client)

**Source:** [github.com/shaferllc/fleet-idp-client](https://github.com/shaferllc/fleet-idp-client)

**Identity provider:** [github.com/shaferllc/fleet-auth](https://github.com/shaferllc/fleet-auth)

**Operator docs:** [docs/wiki](docs/wiki/README.md) (Fleet Social Login / GitHub Wiki mirror — if the [Wiki tab](https://github.com/shaferllc/fleet-idp-client/wiki) is empty, enable **Wikis** under repo Settings and push from `docs/wiki/`; see [Home](docs/wiki/Home.md)).

### Namespace change (from `fleet/idp-client`)

The **`fleet`** vendor on Packagist.org is already taken by another maintainer, so this library is published as **`shaferllc/fleet-idp-client`**. If you previously required **`fleet/idp-client`** (e.g. from a private mirror), replace it in **`composer.json`**:

```bash
composer remove fleet/idp-client
composer require shaferllc/fleet-idp-client:^0.5
```

The PHP API, config keys, and Artisan command **`fleet:idp:configure`** are unchanged.

## Requirements

- PHP **8.3+**
- Laravel **12** or **13** (Illuminate `^12.0|^13.0`)

## Install (Packagist)

```bash
composer require shaferllc/fleet-idp-client:^0.5
```

No extra `repositories` entry is needed. The Laravel service provider is **auto-discovered**.

### Deploy follow-up (staging / production)

**`composer install`** on servers should resolve **`shaferllc/fleet-idp-client`** from **Packagist** (default) and download a **dist** zip — no GitHub SSH keys required on the app host if the GitHub repository is **public**.

If you still see **`git@github.com: Permission denied`**, you are installing **from source**; prefer **`"preferred-install": "dist"`** (already typical in Laravel apps) and ensure the tag exists on Packagist with a **`dist`** URL.

### Monorepo / local path (developers)

If this repo sits next to your app, you can satisfy the dependency from a path checkout (see [repository priority](https://getcomposer.org/repoprio)):

```bash
composer config repositories.fleet-idp-client-dev '{"type":"path","url":"../fleet-idp-client","options":{"symlink":true}}'
composer update shaferllc/fleet-idp-client
```

Remove the repository when you want to match production (`composer config --unset repositories.fleet-idp-client-dev` then `composer update shaferllc/fleet-idp-client`).

### GitHub (VCS) without Packagist

If the package is not on Packagist yet, add:

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/shaferllc/fleet-idp-client" }
],
"require": {
    "shaferllc/fleet-idp-client": "^0.5"
}
```

Remove that block once Packagist lists the package (Composer will use Packagist by default).

### Legacy: private Packeton mirror

Older docs referred to **`fleet/idp-client`** on **packages.shafer.llc**. Migrate using the [namespace change](#namespace-change-from-fleetidp-client) steps above and **`shaferllc/fleet-idp-client`** on Packagist instead.

## Minimal app integration

1. Set **`.env`** — either run **`php artisan fleet:idp:configure`** against Fleet Auth (see [CLI bootstrap](#cli-bootstrap-fleetidpconfigure)) or set `FLEET_IDP_URL`, client id/secret, and redirect path/URI manually (see [Configuration](#configuration)).
2. Choose **`FLEET_IDP_WEB_MODE`**: `eloquent` (default, Breeze-style login + `Auth::login`) or `session` (Fleet Console: session flags + IdP user array).
3. Drop **`x-fleet-idp::oauth-button`** (with `variant`) on your login screen — **`href` is optional**; it defaults to the package redirect route.

The package registers **`GET`** OAuth **start** and **callback** routes (see `routes/web.php`). Set `FLEET_IDP_WEB_ENABLED=false` if you register routes yourself.

**Password grant** (email/password against Fleet Auth) is still a few lines in your login action: call `FleetIdpPasswordGrant::attempt()` before local `Auth::attempt()` (see Waypost’s Volt login).

## CLI bootstrap (`fleet:idp:configure`)

From **0.4.0** (Composer package **`shaferllc/fleet-idp-client`** from **0.5.0**), the package registers an Artisan command that calls Fleet Auth’s **`POST /api/cli/setup`** and merges returned credentials into your app’s **`.env`**.

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

## GitHub / Google (Socialite)

The package registers **`GET oauth/{provider}`** and **`oauth/{provider}/callback`** (GitHub + Google) when **`FLEET_IDP_SOCIALITE_ENABLED`** is true. Configure **`services.github`** and **`services.google`** in your app as usual (`GITHUB_*` / `GOOGLE_*` in `.env`).

Use **`x-fleet-idp::social-login-buttons`** (prop **`variant`**: `waypost` or `console`) next to your email form. It includes the Fleet OAuth button when configured, plus GitHub/Google when:

1. The provider has client id + secret in `config/services.php`, and  
2. Fleet Auth **`GET /api/social-login/providers?client_id={FLEET_IDP_CLIENT_ID}`** returns **`true`** for that key (cached; see `fleet_idp.socialite.policy_cache_seconds`). **`FLEET_IDP_CLIENT_ID`** must match the Passport **authorization-code** client for this app.

Per-app toggles: **Fleet Auth → Admin → Integrations** (each OAuth client block) or **Edit OAuth client**. Without **`?client_id=`**, the IdP returns env defaults only (**`FLEET_AUTH_SOCIAL_GITHUB`**, **`FLEET_AUTH_SOCIAL_GOOGLE`**).

If **`FLEET_IDP_URL`** is empty, policy checks are skipped (only local `services.*` matter). Set **`FLEET_IDP_SOCIALITE_POLICY_FAIL_OPEN=false`** to hide social buttons when the IdP cannot be reached.

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
