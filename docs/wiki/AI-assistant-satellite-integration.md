# AI assistant: integrate Fleet Auth on a Laravel satellite

Use this page as **context for coding agents** (Cursor, Codex, etc.) after the human runs Composer and publishing commands. It summarizes what **`shaferllc/fleet-idp-client`** already provides and what **the app must own** (routes, Livewire/Volt pages, middleware wiring, profile UI).

## Human prerequisites (run in the app repo)

1. **Require the package** (Packagist: `shaferllc/fleet-idp-client`).
2. **Publish themed surfaces** (views + lang + optional layout stub):

   ```bash
   php artisan fleet:idp:install
   ```

   Add **`--force`** to overwrite on upgrade. Add **`--with-config`** if the team wants `config/fleet_idp.php` in git. Add **`--with-migrations`** only when migrating off package auto-loaded migrations (see `FLEET_IDP_EMAIL_SIGN_IN_LOAD_MIGRATIONS` in package config comments).

3. **Bootstrap credentials** against Fleet Auth:

   ```bash
   php artisan fleet:idp:configure --url=https://fleet-auth.example --token="$FLEET_AUTH_CLI_SETUP_TOKEN" --name="App Name"
   ```

4. **Migrate** — package may auto-load email-sign-in migrations; run `php artisan migrate`.

## What the package already registers (do not duplicate)

- **OAuth** start + callback routes (`FLEET_IDP_WEB_ENABLED`, `fleet_idp.web.*`).
- **Account** forgot / reset / change password routes when `fleet_idp.account.enabled` is true (`routes/account.php`).
- **Email sign-in** magic link callback route (`login.email-magic` by default) and profile confirm routes (`profile-email-sign-in-confirm.php`).
- **Socialite** GitHub/Google routes when enabled (`fleet_idp.socialite.*`).
- **Blade namespace** `fleet-idp::…`; **published overrides** live under `resources/views/vendor/fleet-idp/` (prepended, so they win).
- **Components**: `<x-fleet-idp::oauth-button />`, `<x-fleet-idp::social-login-buttons />`, `<x-fleet-idp::managed-password-notice />`, `<x-fleet-idp::confirm-current-password-modal />` (confirm modal for sensitive toggles).

## Your job as the assistant: wire the app

### 1. Middleware (`bootstrap/app.php` or equivalent)

- Alias **`verified`** to **`Fleet\IdpClient\Http\Middleware\EnsureSatelliteEmailIsVerified`** when Fleet Auth can require verified email (policy-driven).
- Append **`Fleet\IdpClient\Http\Middleware\EnsureFleetSiteRequiresTwoFactor`** to the **`web`** group when the product should enforce IdP “require 2FA” on the satellite.
- If the app uses **`Fleet\IdpClient\Http\FleetSatelliteWebMiddleware::register()`**, call it **once** inside `withMiddleware` and **do not** overwrite the middleware alias map afterward (Laravel replaces the whole map on each `alias()` call).

### 2. `User` model (`fleet_idp.user_model`, usually `App\Models\User`)

- **OAuth sync** uses **`Fleet\IdpClient\FleetIdpEloquentUserProvisioner`**: ensure **`provider`**, **`provider_id`**, **`email`**, **`name`**, and nullable **`password`** work with mass assignment / `$fillable` as needed.
- Add columns / casts for email code + magic link preferences and **pending confirmation** token hashes if profile confirm is enabled (see package migrations and `fleet_idp.email_sign_in.profile_confirm.columns`).

### 3. Login UI (app-owned)

- Add **`<x-fleet-idp::oauth-button />`** (and optionally **`<x-fleet-idp::social-login-buttons />`**) to the login view.
- For **email + password against Fleet Auth**, call **`Fleet\IdpClient\FleetIdpPasswordGrant::attempt($email, $password)`** when configured, before local **`Auth::attempt`**.
- Respect **`Fleet\IdpClient\Services\FleetSocialLoginPolicy`**: hide email code / magic link entry points when the IdP disables them; use **`allowTwoFactor()`**, **`requireTwoFactor()`**, **`requireEmailVerification()`**, **`respectLocalTotpForSessions()`** for branching (2FA challenge session, verified middleware, etc.).
- For **email code login page**, use **`Fleet\IdpClient\FleetEmailSignIn`** and **`Fleet\IdpClient\FleetEmailSignInSession`** (or bind **`EmailSignInSessionCompleter`** for custom post-login / 2FA handoff).

### 4. Profile / security (app-owned)

- Toggles for **numeric code** and **magic link** (when policy allows), calling package support classes for **pending confirm** flows; reuse **`<x-fleet-idp::confirm-current-password-modal />`** when turning **off** a method requires password confirmation (product pattern).
- Password change: if the user is Fleet-managed, use **`Fleet\IdpClient\FleetIdp::attemptFleetPasswordChange()`** (or redirect via **`FleetIdp::idpChangePasswordUrl()`**) and show **`<x-fleet-idp::managed-password-notice />`** where appropriate.

### 5. Layout and branding

- Set **`fleet_idp.account.layout`** (env **`FLEET_IDP_ACCOUNT_LAYOUT`** or `AppServiceProvider`) to the app’s guest layout (e.g. **`layouts.guest`**) **if** that layout supports **`@yield('content')`** for package account views.
- **Theme** published files under **`resources/views/vendor/fleet-idp/`** using the same input/button components as the rest of the app (see [Publishing views and styling](Publishing-views-and-styling)).

### 6. Config the app may set in `AppServiceProvider`

Examples (adjust names to your schema):

- `fleet_idp.account.views.forgot_password` / `reset_password` → app-owned Blade under `resources/views/auth/…` if you prefer not to edit vendor-prefixed copies.
- `fleet_idp.email_sign_in.user_code_enabled_attribute` / `user_magic_link_enabled_attribute` when using **split** columns instead of a single `email_code_login_enabled` flag.
- `fleet_idp.email_sign_in.mutually_exclusive_code_and_magic` when product requires only one method enabled at a time.
- `fleet_idp.email_sign_in.profile_confirm.interstitial_layout` for the email-link confirm screen.

## Files to open after `fleet:idp:install`

| Path | Purpose |
|------|---------|
| `resources/views/vendor/fleet-idp/account/*.blade.php` | Forgot / reset / change password |
| `resources/views/vendor/fleet-idp/account/partials/*` | Fleet reset confirmation steps |
| `resources/views/vendor/fleet-idp/components/*` | OAuth button variants, managed password notice, social buttons |
| `resources/views/vendor/fleet-idp/profile-email-sign-in-confirm.blade.php` | Profile confirm interstitial |
| `lang/vendor/fleet-idp/` | Copy and tone |

## Copy-paste prompt starter (for the human)

> Laravel app uses `shaferllc/fleet-idp-client`. We ran `php artisan fleet:idp:install` and `fleet:idp:configure`. Published views are under `resources/views/vendor/fleet-idp/`. Wire login (OAuth button + optional password grant + email code page), profile toggles, and `bootstrap/app.php` middleware per `docs/wiki/AI-assistant-satellite-integration.md`. Match existing Livewire 4 patterns and layout `layouts.guest`. Do not re-register package routes.

## Reference implementation

**Waypost** (open source) mirrors the intended satellite patterns: `routes/auth.php`, Livewire auth pages, `AppServiceProvider` `fleet_idp.*` overrides, and `bootstrap/app.php` middleware. Treat it as an example, not a dependency.

## Related wiki pages

- [Publishing views and styling](Publishing-views-and-styling)
- [Account and password](Account-and-password)
- [Custom account views (reset + profile)](Custom-account-views)
- [Email code & magic-link login (spec)](Email-code-and-magic-link-login)
- [Configuration reference](Configuration-reference)
