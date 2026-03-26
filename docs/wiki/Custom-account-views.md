# Custom account views (reset password + profile password)

This guide explains how to reuse **Breeze-style** Blade (and a **Livewire Volt** profile form) in **any Laravel satellite** that uses **`shaferllc/fleet-idp-client`**, so forgot/reset/change flows match your app shell while Fleet-linked users are handled correctly.

A full reference implementation lives in **Waypost**:

- `resources/views/auth/reset-password.blade.php`
- `resources/views/auth/forgot-password.blade.php` (same pattern: app-owned view + `fleet_idp.account.views.forgot_password`)
- `resources/views/livewire/profile/update-password-form.blade.php`

Copy those files into your app and adjust classes to your design system.

---

## Prerequisites

1. **`composer require shaferllc/fleet-idp-client`** and complete [Configuration reference](Configuration-reference) / [Account and password](Account-and-password) (OAuth, `FLEET_IDP_URL`, optional provisioning token, `users.provider` / `users.provider_id` for Fleet-linked rows).
2. Package **account routes** enabled (default): forgot, reset, fleet-send, etc.
3. **Layout** that supports Blade **`@section('content')`** (and **`@section('title')`** if you set titles). Often **`layouts.guest`** for reset/forgot.
4. **Blade components** similar to Laravel Breeze: `x-input-label`, `x-text-input`, `x-input-error`, `x-primary-button`. If you use different names, find/replace in the copied views.
5. For the profile recipe: **Livewire 3** + **Volt** (or translate the Volt single-file component into a classic Livewire component + view).

---

## 1. Reset password — `auth/reset-password.blade.php`

### What it does

Renders the **“choose a new password”** form for the **local** password broker (`POST` handled by **`LocalResetPasswordController`**). Fleet-triggered resets still complete on **Fleet Auth**; this screen is for users whose reset email pointed at **your** app’s `/reset-password/{token}` URL.

### Wire the package to your view

In a service provider’s **`boot()`** (or published **`config/fleet_idp.php`**):

```php
config([
    'fleet_idp.account.layout' => 'layouts.guest',
    'fleet_idp.account.views.reset_password' => 'auth.reset-password',
]);
```

Or set env **`FLEET_IDP_ACCOUNT_VIEW_RESET_PASSWORD=auth.reset-password`** (see package `config/fleet_idp.php`).

### View contract

| Item | Value |
|------|--------|
| **Extends** | `config('fleet_idp.account.layout', 'layouts.guest')` |
| **Form action** | `route(config('fleet_idp.account.route_names.reset_store', 'password.update'))` |
| **Hidden fields** | `token` (from `$token`), CSRF |
| **Inputs** | `email` (prefill `old('email', $email)`), `password`, `password_confirmation` |
| **Strings** | Prefer `trans('fleet-idp::account.*')` for reset title, intro, labels, submit, back to login |

The controller passes **`$token`** and query **`email`** into the view.

### Styling notes

- Replace **`text-ink`**, **`text-sage-dark`**, **`bg-sage`**, etc. with your Tailwind/theme tokens.
- **`wire:navigate`** on the login link is optional (omit if you do not use Livewire SPA navigation).

### Related env

- **`FLEET_IDP_ROUTE_PASSWORD_RESET`** / **`FLEET_IDP_ROUTE_PASSWORD_UPDATE`** — only if you renamed the default route names.

---

## 2. Profile “update password” — Livewire Volt (`update-password-form.blade.php`)

### What it does

- If **`FleetIdp::passwordManagedByIdp($user)`** is **true** (user row is Fleet-linked per **`fleet_idp.provider_name`**, usually `fleet_auth`), the form is **hidden** and **`x-fleet-idp::managed-password-notice`** prompts the user to change password on Fleet Auth.
- Otherwise it runs a normal **current password + new password** update on the local user model.

### Dependencies

- **`Fleet\IdpClient\FleetIdp`** — `passwordManagedByIdp()` (wraps package routing rules; respects **`FLEET_IDP_LOCAL_PASSWORD_ONLY`** and IdP URL).
- **Package Blade component** — `<x-fleet-idp::managed-password-notice />` (optional `button-class` for your button styles).

### Where to put the file

Typical Volt path:

`resources/views/livewire/profile/update-password-form.blade.php`

Mount it from your profile/settings page, e.g.:

```blade
<livewire:profile.update-password-form />
```

(Ensure the Volt discover path / namespace matches your app’s Livewire config.)

### Behaviour summary (PHP block)

| Condition | Action |
|-----------|--------|
| Fleet-managed user | `updatePassword()` returns immediately; no local password change |
| User has `password === null` | Omit “current password” field; allow setting first password (e.g. social-only account) |
| Otherwise | Require `current_password` with Laravel’s **`current_password`** rule |

After success, dispatch **`password-updated`** for your **`x-action-message`** (or equivalent flash).

### If you do not use Volt

Extract the **`class extends Component`** logic into **`App\Livewire\Profile\UpdatePasswordForm`** (or similar) and keep the Blade markup as a normal **`resources/views/livewire/...`** view; call **`FleetIdp::passwordManagedByIdp()`** the same way.

---

## 3. Forgot password (same pattern as reset)

Point the package at an app-owned view so you are not stuck with the minimal default:

```php
'fleet_idp.account.views.forgot_password' => 'auth.forgot-password',
```

That view must handle **three** UI states driven by session keys from **`LocalForgotPasswordController`**:

- **`fleet_idp_fleet_reset_confirm`** — confirm before calling Fleet provisioning
- **`fleet_idp_pending_fleet_reset`** — manual “open Fleet Auth” fallback + optional **`provision_error`** hints
- Default — email field + **`password.email`** POST

See Waypost’s **`auth/forgot-password.blade.php`** for a full example.

---

## 4. Checklist for a new satellite

- [ ] **`fleet_idp.account.layout`** matches your guest/settings shell (`@yield('content')`).
- [ ] **`fleet_idp.account.views.reset_password`** (and optionally **`forgot_password`**) set to your Blade paths.
- [ ] Breeze-style **`x-*`** components exist or views updated to your equivalents.
- [ ] Profile/settings includes **Fleet-aware** password UI (`FleetIdp::passwordManagedByIdp` + **`managed-password-notice`**).
- [ ] Feature tests: local reset, Fleet confirm + provisioning fake (see [Testing satellites](Testing-satellites)).

## Related

- [Publishing views and styling](Publishing-views-and-styling) — publish tags vs app-owned paths.
- [Account and password](Account-and-password) — flow and env vars.
- Package README — install and `fleet:idp:configure`.
