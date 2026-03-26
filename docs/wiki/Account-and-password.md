# Account and password (forgot, reset, change)

Satellite apps register **guest** routes for forgot password, reset password, and **auth** routes for change password via the package’s `routes/account.php`. Logic lives in **`shaferllc/fleet-idp-client`** so every app behaves consistently; **presentation** should be customized by [publishing views](Publishing-views-and-styling).

## Forgot password (high level)

1. User enters **email** on your app (always on the satellite UI).
2. The app looks up **`fleet_idp.user_model`** by email.

### Fleet-managed or Fleet-only email

- **Fleet-linked row** (`users.provider` equals **`fleet_idp.provider_name`**, usually `fleet_auth`), **or**
- **No local row** but **`POST /api/provisioning/users/lookup`** returns **`exists: true`** (requires provisioning token), **or**
- **Local row not Fleet-linked** or **no local row** when the email’s domain is listed in **`FLEET_IDP_LIKELY_EMAIL_DOMAINS`** (comma-separated, e.g. `fleet.test`) — optional heuristic so Fleet-style addresses still get the confirm step,

→ after the user confirms, the satellite calls **`POST /api/provisioning/users/password-reset`** on Fleet Auth with the **same Bearer** as provisioning. Fleet runs its normal password broker and **sends the reset email from the IdP**. The user stays on your app and sees a **success flash** (`fleet_reset_link_sent`).

The confirm payload is stored in the session with **`session()->put`** (not one-request flash) so a normal browser flow (POST → GET forgot page → POST fleet-send) still works.

If that API call **fails** (no token, network, non-2xx), the app falls back to a **confirmation screen** with a manual link to Fleet’s public forgot-password page, operator hints when diagnostics are available (`provision_error`), and copy explaining the automatic send did not work (`reason: api_unavailable`).

### Local-only account

- **Local row** that is **not** Fleet-linked → Laravel **`Password::sendResetLink`** on **this** app (local broker + notification).

### Unknown email, lookup says no Fleet user

- Show the usual **vague** success message (no mail from either side).

## Reset link in email (important)

Fleet-triggered resets still use **Fleet Auth’s** reset URL in the message body (token is issued by the IdP). Completing the new password happens on **Fleet Auth**, not on the satellite—only the **request** step stays on your app.

## Change password (signed in)

- **Fleet-linked user** — controller **redirects** to Fleet Auth change-password URL (configurable path).
- **Other users** — local form from **`account/change-password.blade.php`**.

In settings/profile UI, use **`FleetIdp::passwordManagedByIdp($user)`** or **`<x-fleet-idp::managed-password-notice />`** so users are not shown a useless local password form when the IdP owns the credential.

## Environment variables (account)

| Variable | Role |
|----------|------|
| `FLEET_IDP_ACCOUNT_ROUTES_ENABLED` | `false` disables package account routes entirely. |
| `FLEET_IDP_LOCAL_PASSWORD_ONLY` | `true` forces local broker + local change for everyone (dev / special cases). |
| `FLEET_IDP_ACCOUNT_LAYOUT` | Blade layout for account views (e.g. `layouts.guest`). |
| `FLEET_IDP_ACCOUNT_VIEW_RESET_PASSWORD` | Blade for the reset-password token form (default package view). |
| `FLEET_IDP_ACCOUNT_AUTO_LAYOUT` | When true, use `layouts.fleet-idp-account` if that view exists and layout is still package default. |
| `FLEET_IDP_ACCOUNT_MIDDLEWARE` | Comma-separated middleware (default `web`). |
| `FLEET_IDP_ACCOUNT_ROUTE_PREFIX` | Optional URL prefix for all account routes. |
| `FLEET_IDP_IDP_FORGOT_PASSWORD_PATH` | Path on Fleet Auth for **fallback** manual link only. |
| `FLEET_IDP_IDP_RESET_PASSWORD_PATH` | Template with `{token}` if you customize. |
| `FLEET_IDP_IDP_CHANGE_PASSWORD_PATH` | Fleet change-password path (default `/account/password`). |
| `FLEET_IDP_ROUTE_PASSWORD_REQUEST` | Laravel route name for GET forgot (default `password.request`). |
| `FLEET_IDP_LIKELY_EMAIL_DOMAINS` | Comma-separated domains for the Fleet confirm heuristic (optional). |
| `FLEET_IDP_PROVISIONING_VERIFY_SSL` | TLS verify for provisioning HTTP (default `true`; `false` local-only if needed). |

Provisioning token (**`FLEET_AUTH_PROVISIONING_TOKEN`**) must be set for **lookup** and **password-reset** API calls. **`FLEET_AUTH_PROVISIONING_PASSWORD_RESET_URL`** overrides the default password-reset endpoint URL.

## Layouts and Livewire

If your **guest** layout is built for Volt/Livewire with **`{{ $slot }}`**, add a branch so classic Blade children can use **`@section('content')`**:

```blade
@hasSection('content')
    @yield('content')
@else
    {{ $slot }}
@endif
```

Use **`@yield('title', config('app.name'))`** in `<title>` if account views set **`@section('title', …)`**.

## Related

- [Email code & magic-link login (spec)](Email-code-and-magic-link-login) — optional passwordless login; per-site mode on Fleet Auth.
- [Custom account views (reset + profile)](Custom-account-views) — copy Waypost-style templates into other satellites.
- [Provisioning and Fleet lookup](Provisioning-and-Fleet-lookup)
- [Publishing views and styling](Publishing-views-and-styling)
- [Testing satellites](Testing-satellites)
