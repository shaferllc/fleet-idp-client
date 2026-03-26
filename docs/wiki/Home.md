# Fleet identity wiki

Reference for **Fleet Auth** (Passport IdP) and **`shaferllc/fleet-idp-client`** (satellite apps: Waypost, Fleet Console, etc.).

**Browse on GitHub:** [github.com/shaferllc/fleet-idp-client/wiki](https://github.com/shaferllc/fleet-idp-client/wiki)

## Pages

| Page | What it covers |
|------|----------------|
| [Fleet Social Login (GitHub / Google)](Fleet-Social-Login) | Third-party OAuth on client apps, per-client Passport toggles, policy API, env vars, troubleshooting |

## Source of truth (package repo)

Canonical markdown lives in **[`docs/wiki/`](https://github.com/shaferllc/fleet-idp-client/tree/main/docs/wiki)** on the main branch. Edit there (PRs), then sync to this wiki when you change operator docs.

## Sync `docs/wiki/` → GitHub Wiki

Wiki remote: **`https://github.com/shaferllc/fleet-idp-client.wiki.git`** (branch is usually **`master`**).

```bash
git clone https://github.com/shaferllc/fleet-idp-client.wiki.git fleet-idp-wiki
cd fleet-idp-wiki
cp /path/to/fleet-idp-client/docs/wiki/Home.md .
cp /path/to/fleet-idp-client/docs/wiki/Fleet-Social-Login.md .
cp /path/to/fleet-idp-client/docs/wiki/_Sidebar.md .
git add -A
git commit -m "Sync wiki from docs/wiki"
git push origin master
```

If your wiki uses **`main`**, run `git push origin main` instead.

## Related repos

| Area | Repo / path |
|------|-------------|
| Identity provider | [shaferllc/fleet-auth](https://github.com/shaferllc/fleet-auth) |
| This Composer package | [shaferllc/fleet-idp-client](https://github.com/shaferllc/fleet-idp-client) |

The package **README** has shorter install and Socialite notes; this wiki goes deeper for operators.
