# Configuration reference

Config is merged from the package **`config/fleet_idp.php`**. Override with **`.env`** or publish **`fleet-idp-config`** and edit **`config/fleet_idp.php`**.

Below is a **practical** grouping for operators. For every key, the published config file remains authoritative if values differ.

## Core IdP connection

| Env | Purpose |
|-----|---------|
| `FLEET_IDP_URL` | Fleet Auth **root** URL only (no `/oauth/...` path). Empty disables IdP-backed behaviour that depends on a base URL. |
| `FLEET_IDP_CLIENT_ID` / `FLEET_IDP_CLIENT_SECRET` | Passport **authorization-code** client used for web OAuth. |
| `FLEET_IDP_REDIRECT_URI` | Full callback URL; if unset, derived from request + `FLEET_IDP_REDIRECT_PATH` or `APP_URL`. |
| `FLEET_IDP_REDIRECT_PATH` | Callback path only (default `/oauth/fleet-auth/callback`). |
| `FLEET_IDP_PASSWORD_CLIENT_ID` / `FLEET_IDP_PASSWORD_CLIENT_SECRET` | Optional password grant client. |
| `FLEET_IDP_USER_MODEL` | Eloquent user class (e.g. `App\Models\User`). |
| `FLEET_IDP_PROVIDER_NAME` | Value stored in `users.provider` for Fleet-linked rows (default `fleet_auth`). |
| `FLEET_IDP_SESSION_STATE_KEY` | Session key for OAuth `state` CSRF token. |

## Web OAuth (`routes/web.php`)

| Env | Purpose |
|-----|---------|
| `FLEET_IDP_WEB_ENABLED` | `false` to register OAuth routes yourself. |
| `FLEET_IDP_WEB_MODE` | `eloquent` (default) or `session` (console-style). |
| `FLEET_IDP_OAUTH_START_PATH` | Browser path to start OAuth. |
| `FLEET_IDP_WEB_MIDDLEWARE` | Comma-separated middleware. |
| `FLEET_IDP_ROUTE_OAUTH_REDIRECT` / `CALLBACK` / `FAILURE` | Route names. |
| `FLEET_IDP_OAUTH_FAILURE_PATH` | Path for failure page. |
| `FLEET_IDP_POST_LOGIN_ROUTE` | After successful OAuth (eloquent). |
| `FLEET_IDP_TWO_FACTOR_ROUTE` | When satellite enforces 2FA after IdP login. |
| `FLEET_IDP_OAUTH_ERROR_ROUTE` | Where to send Socialite/OAuth errors (e.g. `login` or package failure route). |

See package config for **session mode** keys (`FLEET_IDP_SESSION_*`).

## Provisioning & lookup

| Env | Purpose |
|-----|---------|
| `FLEET_AUTH_PROVISIONING_TOKEN` | Bearer for Fleet Auth **`POST /api/provisioning/users`**, **`.../lookup`**, and **`.../password-reset`**. |
| `FLEET_AUTH_PROVISIONING_URL` | Full URL for create user; default `{FLEET_IDP_URL}/api/provisioning/users`. |
| `FLEET_AUTH_PROVISIONING_LOOKUP_URL` | Optional override for lookup endpoint. |
| `FLEET_AUTH_PROVISIONING_PASSWORD_RESET_URL` | Optional override for password-reset endpoint. |

Details: [Provisioning and Fleet lookup](Provisioning-and-Fleet-lookup).

## Account / password routes

See [Account and password](Account-and-password) for behaviour. Env names follow **`FLEET_IDP_ACCOUNT_*`**, **`FLEET_IDP_ROUTE_PASSWORD_*`**, **`FLEET_IDP_IDP_*`** paths, **`FLEET_IDP_LOCAL_PASSWORD_ONLY`**, etc.

## Socialite (GitHub / Google)

| Env | Purpose |
|-----|---------|
| `FLEET_IDP_SOCIALITE_ENABLED` | Register Socialite routes. |
| `FLEET_IDP_SOCIALITE_POLICY_FAIL_OPEN` | If IdP unreachable, still show buttons when local `services.*` configured. |
| `FLEET_IDP_SOCIALITE_POLICY_CACHE` | Seconds to cache **`GET /api/social-login/providers`**. |

Full flow: [Fleet Social Login (GitHub / Google)](Fleet-Social-Login).

## CLI bootstrap

`fleet:idp:configure` writes many of the above from Fleet Auth **`POST /api/cli/setup`**. See package README **CLI bootstrap** section.

## Related

- [Publishing views and styling](Publishing-views-and-styling)
- [Troubleshooting](Troubleshooting)
