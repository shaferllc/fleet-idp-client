# fleet/idp-client

Laravel package: OAuth2 authorization-code flow + optional Passport password grant against **Fleet Auth**, plus Eloquent user provisioning.

## Install (monorepo / path)

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

- `FLEET_IDP_URL` — Fleet Auth **root** only (e.g. `https://fleet-auth.test`).
- `FLEET_IDP_CLIENT_ID` / `FLEET_IDP_CLIENT_SECRET` — OAuth authorization-code client.
- `FLEET_IDP_REDIRECT_URI` — must match Passport `redirect_uris` (default: `{APP_URL}/oauth/fleet-auth/callback`; Fleet Console typically uses `{APP_URL}/auth/callback`).
- Optional password grant: `FLEET_IDP_PASSWORD_CLIENT_ID`, `FLEET_IDP_PASSWORD_CLIENT_SECRET`, and `FLEET_IDP_USER_MODEL` (Eloquent user class).

Optional publish:

```bash
php artisan vendor:publish --tag=fleet-idp-config
php artisan vendor:publish --tag=fleet-idp-lang
```

## Usage in an app

- **HTTP / OAuth:** `Fleet\IdpClient\FleetIdpOAuth` — `isConfigured()`, `authorizationRedirectUrl()`, `exchangeCode()`, `fetchUser()`, `fetchUserWithToken()`, `requireIdpRootUrl()`.
- **Password grant + sync:** `Fleet\IdpClient\FleetIdpPasswordGrant::attempt($email, $password)` returns the local Eloquent user or `null`.
- **Sync only:** `Fleet\IdpClient\FleetIdpEloquentUserProvisioner::syncFromRemoteUser($remote)` — expects IdP `/api/user` JSON shape.

Routes, controllers, and Blade/Livewire views stay in each application (see Waypost and Fleet Console in this repo).
