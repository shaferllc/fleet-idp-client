# fleet/idp-client

Laravel package: OAuth2 **authorization-code** flow + optional Passport **password grant** against **Fleet Auth**, plus Eloquent user provisioning from the IdP `/api/user` payload.

**Repository:** [github.com/shaferllc/fleet-idp-client](https://github.com/shaferllc/fleet-idp-client)

**Identity provider:** [github.com/shaferllc/fleet-auth](https://github.com/shaferllc/fleet-auth)

## Requirements

- PHP **8.3+**
- Laravel **12** or **13** (Illuminate components `^12.0|^13.0`)

## Install

### From GitHub (clone-friendly)

```json
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/shaferllc/fleet-idp-client"
    }
],
"require": {
    "fleet/idp-client": "^0.1"
}
```

Until the package is on Packagist, keep the `vcs` entry (or use `"@dev"` with a **branch alias** if you require `dev-main`).

### Monorepo / local path

```json
"repositories": [
    { "type": "path", "url": "../fleet-idp-client", "options": { "symlink": true } }
],
"require": {
    "fleet/idp-client": "@dev"
}
```

Run `composer update fleet/idp-client`. The service provider is auto-discovered.

## Configuration

Config is merged from the package. Override with env (see `config/fleet_idp.php` in this repo):

| Variable | Purpose |
|----------|---------|
| `FLEET_IDP_URL` | Fleet Auth **root** only (e.g. `https://fleet-auth.test`). Never set this to a client callback path. |
| `FLEET_IDP_CLIENT_ID` / `FLEET_IDP_CLIENT_SECRET` | Authorization-code OAuth client |
| `FLEET_IDP_REDIRECT_URI` | Must match a Passport `redirect` URI (default `{APP_URL}/oauth/fleet-auth/callback`; Fleet Console usually sets `{APP_URL}/auth/callback`) |
| `FLEET_IDP_PASSWORD_CLIENT_ID` / `FLEET_IDP_PASSWORD_CLIENT_SECRET` | Optional password grant |
| `FLEET_IDP_USER_MODEL` | Eloquent user class for provisioning (e.g. `App\Models\User`) |

Optional publish:

```bash
php artisan vendor:publish --tag=fleet-idp-config
php artisan vendor:publish --tag=fleet-idp-lang
```

## Usage in an app

- **OAuth:** `Fleet\IdpClient\FleetIdpOAuth` — `isConfigured()`, `authorizationRedirectUrl()`, `exchangeCode()`, `fetchUser()`, `fetchUserWithToken()`, `requireIdpRootUrl()`.
- **Password grant + sync:** `Fleet\IdpClient\FleetIdpPasswordGrant::attempt($email, $password)` returns the local Eloquent user or `null`.
- **Sync only:** `Fleet\IdpClient\FleetIdpEloquentUserProvisioner::syncFromRemoteUser($remote)` — expects IdP `/api/user` JSON.

Routes, controllers, and Blade/Livewire views stay in each application (e.g. [Waypost](https://github.com/shaferllc/waypost), [Fleet Console](https://github.com/shaferllc/fleet-console)).

## License

MIT — see `composer.json`.
