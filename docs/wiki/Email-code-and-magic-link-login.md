# Email code & magic-link login (spec)

Optional **login** methods (not password reset): **numeric code** and/or **magic link**, configured **per satellite site** in **Fleet Auth**. Satellites expose the same UX for **all users** (Fleet-linked or not); **who stores and sends** the challenge depends on where the account lives.

This page records **product decisions** agreed for implementation. Update it when behaviour ships.

---

## Delivery modes (Fleet Auth per site)

| Mode | Behaviour |
|------|------------|
| **Numeric code** | 6–8 digit one-time code emailed to the user. |
| **Magic link** | Single-use URL emailed to the user (one tap). |

Fleet Auth holds **per-site** configuration: each satellite (e.g. Trusted Client Site / integration record) selects **one** mode for that app — **code** or **magic link** (not both simultaneously for the same site).

Satellites **read** the active mode (and whether the feature is enabled at all) from Fleet — e.g. cached policy API similar to social-login providers — so UI and copy match Fleet’s setting.

**Phase 1** can ship numeric codes first; **phase 2** adds magic link as an alternate mode in the same model.

---

## Login UX: one method per attempt (not stacked with this feature)

On the login screen the user picks **one** path for that sign-in — not a chain of password **then** code for this email-code feature:

| Path | When |
|------|------|
| **Password** | User enters email + password (existing broker / Fleet password grant as today). |
| **Email code or magic link** | User has **code/link login** enabled; flow is **passwordless** for that path: email → send code or open link → verify. No password step on this path. |

So it is **password *or* code/link**, not password **and** code for the same login. Users with both a password and code login enabled **choose** which method to use each time (tabs, toggle, or separate buttons — UX detail for the satellite).

**2FA (TOTP, WebAuthn, etc.)** is **out of scope** for this spec: it will be a **separate** step or product area, not mixed with email code/link login.

---

## Source of truth & email sending

| Account type | Source of truth for “code login enabled” | Who sends the email |
|--------------|-------------------------------------------|----------------------|
| **Satellite-only** (not Fleet-linked; credentials / profile owned by satellite) | **Satellite** DB | **Satellite** (`MAIL_*` on the app) |
| **Fleet-linked / IdP-owned** | **Fleet Auth** (synced via `/api/user` or dedicated fields) | **Fleet Auth** (implementation detail: IdP generates challenge and sends mail) |

Satellites call Fleet for **policy** (mode + feature on/off per site) for **all** users; they implement **local** challenge storage, validation, and **mail** for non–Fleet-linked users.

---

## Profile: turning the feature on

- User opts in from **profile / security settings** on the satellite.
- **Verification required:** send code or link (per site mode) to prove inbox control; only after successful verification is the preference persisted.
- If Fleet Auth has **not** enabled this feature for that site, the toggle is **hidden or disabled** with explanation.

---

## Migration: local → Fleet

When a user **links or migrates** to Fleet Auth:

- Their **login preference** (code/link enabled) **moves** to Fleet — **no second enrollment** on Fleet unless product later requires it for other reasons.

---

## Related flows

- **Forgot password** remains separate (existing provisioning / local broker flows).
- **2FA** — separate from email code/link login; not “password + code email” as one feature.

---

## Implementation checklist (high level)

### Fleet Auth

- [ ] Per-site setting: feature on/off, **code vs magic link**.
- [ ] Policy endpoint for satellites (cache-friendly).
- [ ] For Fleet-managed users: issue/verify challenges, send mail, persist user preference.
- [ ] `/api/user` (or equivalent) exposes preference for linked users.
- [ ] Migration hook: accept preference when account is linked from satellite.

### fleet-idp-client

- [ ] Fetch & cache site policy; helpers for satellites.
- [ ] Document / support **parallel** login routes: classic password vs email-code (no stacking of code after password grant for this feature).

### Satellite (e.g. Waypost)

- [ ] Login UI: **password OR code/link** (user chooses method); passwordless path when using code/link.
- [ ] Local challenge table (or cache) + send mail for non–Fleet users.
- [ ] Profile: toggle + verify-on-enable + read policy from Fleet.
- [ ] Tests: local user, Fleet user, policy off, migration.

---

## Related wiki pages

- [Account and password](Account-and-password)
- [Provisioning and Fleet lookup](Provisioning-and-Fleet-lookup)
- [Configuration reference](Configuration-reference)
