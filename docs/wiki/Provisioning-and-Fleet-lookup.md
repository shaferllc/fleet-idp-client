# Provisioning and Fleet lookup

Fleet Auth exposes **server-to-server** endpoints protected by the same **provisioning Bearer** trusted satellites already use to mirror new registrations.

## Create user — `POST /api/provisioning/users`

- **Middleware:** `fleet.provisioning` (valid Bearer: per-site token from Fleet Auth admin **or** legacy **`FLEET_AUTH_PROVISIONING_TOKEN`** env on IdP).
- **Body:** `name`, `email`, `password` (validated; password rules apply).
- **Responses:** `201` + `{ "status": "created" }`, or `200` + `{ "status": "exists" }` if email already present (idempotent).

The package listener **`ProvisionRegisteredUserOnFleetAuth`** POSTs here after **`Registered`** when **`FLEET_AUTH_PROVISIONING_TOKEN`** is set on the satellite and a plain password is available on the request (see **`fleet_idp.provisioning.password_request_keys`**).

Default URL on the satellite: **`{FLEET_IDP_URL}/api/provisioning/users`** unless **`FLEET_AUTH_PROVISIONING_URL`** overrides.

## Lookup email — `POST /api/provisioning/users/lookup`

- **Same Bearer** as create user.
- **Body:** `{ "email": "user@example.com" }` (lowercased by validation).
- **Response:** `{ "exists": true | false }`.

Used by **`FleetProvisioningUserLookup::emailExistsOnFleet()`** during **forgot password** when:

- There is **no** local user row for that email,
- **`FLEET_IDP_URL`** is set,
- **`FLEET_IDP_LOCAL_PASSWORD_ONLY`** is false,
- **`FLEET_AUTH_PROVISIONING_TOKEN`** is set on the satellite.

If the token is missing or the HTTP call fails, the app treats the address as **not** on Fleet for the purpose of the forgot-password branch (ambiguous success for unknown emails).

### URL resolution on the satellite

1. **`FLEET_AUTH_PROVISIONING_LOOKUP_URL`** if set (full URL).  
2. Else **`{FLEET_AUTH_PROVISIONING_URL}/lookup`** (trim + append).  
3. Else **`{FLEET_IDP_URL}/api/provisioning/users/lookup`**.

## Request password reset — `POST /api/provisioning/users/password-reset`

- **Same Bearer** as create user / lookup.
- **Body:** `{ "email": "user@example.com" }` (lowercased by validation).
- **Response:** **`200`** + `{ "status": "accepted" }` always (same privacy model as Fleet’s web forgot form: no enumeration in the JSON).

Fleet runs **`Password::sendResetLink`** for that email. If the user exists, they receive the **normal Fleet Auth** reset notification; if not, nothing is sent, but the JSON is still **`accepted`**.

Satellites call this from **`FleetProvisioningPasswordReset::request()`** (bool) or **`FleetProvisioningPasswordReset::attempt()`** (returns `ok`, `error`, `http_status` for UI hints) so users **submit the forgot form on the client app** without opening Fleet’s forgot page in the browser. The **link inside the email** still points at Fleet Auth (where the token is valid).

Use **`FLEET_IDP_URL` with the same scheme as in the browser** (often `https://…`). If the URL uses `http://` but the server redirects to HTTPS, some HTTP clients drop the `Authorization` header on redirect and Fleet returns **401**.

Optional on the satellite: **`FLEET_IDP_PROVISIONING_VERIFY_SSL`** (default `true`) — set `false` in local dev only if TLS verification fails between apps.

### URL resolution on the satellite

1. **`FLEET_AUTH_PROVISIONING_PASSWORD_RESET_URL`** if set (full URL).  
2. Else **`{FLEET_AUTH_PROVISIONING_URL}/password-reset`** (trim + append).  
3. Else **`{FLEET_IDP_URL}/api/provisioning/users/password-reset`**.

## Security notes

- These endpoints are **not** public; they require a **long random Bearer** rotated via Fleet Auth admin when needed.
- Lookup reveals whether an email exists on Fleet to **holders of the token** only (your satellite backend). Use it only for routing the forgot-password flow (whether to call password-reset for “no local row” cases).
- The password-reset endpoint should only be called from **your** backends (Bearer never exposed to browsers).
- Rate-limit and monitor your satellite’s outbound calls in production if needed.

## Related

- [Account and password](Account-and-password)
- [Configuration reference](Configuration-reference)
- Fleet Auth repo: provisioning routes and **`TrustedClientSite`** model.
