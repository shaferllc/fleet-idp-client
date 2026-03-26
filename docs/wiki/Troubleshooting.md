# Troubleshooting

## OAuth / redirect

| Symptom | Things to check |
|---------|-----------------|
| **`invalid_client`** or redirect mismatch | **`FLEET_IDP_REDIRECT_URI`** (or derived URI) must **exactly** match a value on the Passport client in Fleet Auth. Scheme (`http` vs `https`) and host must match what the browser uses. |
| Loop or empty `href` on OAuth button | IdP not configured; route missing; Livewire attribute bag overwriting `href` — package **`oauth-button`** excludes `href` from automatic merges for this reason. |
| Failure page not styled | Publish **`fleet-idp-views`** and theme **`oauth-failure.blade.php`**, or set **`FLEET_IDP_OAUTH_ERROR_ROUTE=login`** to flash errors on your login view only. |

## Forgot password

| Symptom | Things to check |
|---------|-----------------|
| Page looks unstyled | [Publish views](Publishing-views-and-styling) and set **`FLEET_IDP_ACCOUNT_LAYOUT`** to your guest/marketing layout. |
| No Fleet confirmation for unknown email | **`FLEET_AUTH_PROVISIONING_TOKEN`** must be set; lookup must return **`exists: true`**. Check Fleet Auth logs and outbound HTTP from satellite. |
| Local user not receiving mail | **`FLEET_IDP_LOCAL_PASSWORD_ONLY`**, mail config, and **`Password`** broker / `User` model **`CanResetPassword`**. |
| Fleet-linked user still gets local reset email | `users.provider` must match **`FLEET_IDP_PROVIDER_NAME`**; `local_password_only` must be false; IdP URL set. |

## Social / GitHub / Google

See [Fleet Social Login (GitHub / Google)](Fleet-Social-Login): **`client_id`**, **`services.*`**, IdP toggles, cache TTL.

## Provisioning

| Symptom | Things to check |
|---------|-----------------|
| **`503`** from Fleet | Provisioning not configured on IdP (no trusted site token / legacy env). |
| **`401`** | Wrong Bearer on satellite. |
| User not created | Listener skipped: no token, no plain password on **`Registered`** request (see **`password_request_keys`**). |

## Config cache

After changing **`.env`**, run **`php artisan config:clear`** (or **`config:cache`** in deploy). **`env()`** is not reliable inside cached config in random app code — prefer **`config()`** in application layers.

## Getting help

- Package **README** — install, CLI, high-level config.
- This wiki — operator depth.
- **Fleet Auth** repo — IdP behaviour, admin UI, Passport clients.
