# Fleet identity wiki

Reference for **Fleet Auth** (Passport IdP) and **`shaferllc/fleet-idp-client`** (satellite apps: Waypost, Fleet Console, etc.).

**Browse on GitHub:** [github.com/shaferllc/fleet-idp-client/wiki](https://github.com/shaferllc/fleet-idp-client/wiki)

## Pages

### Styling and UX

| Page | What it covers |
|------|----------------|
| [**Publishing views and styling**](Publishing-views-and-styling) | **Publish `fleet-idp-views` (and usually `fleet-idp-lang`)**; which Blade files to theme; layout and checklist. |
| [**Account and password**](Account-and-password) | Forgot / reset / change password, Fleet confirmation step, env vars, layouts vs Livewire guest. |

### Identity flows

| Page | What it covers |
|------|----------------|
| [**Fleet Social Login (GitHub / Google)**](Fleet-Social-Login) | Third-party OAuth on satellites, per-client Passport toggles, policy API, env vars. |
| [**Provisioning and Fleet lookup**](Provisioning-and-Fleet-lookup) | `POST /api/provisioning/users`, `POST .../users/lookup`, Bearer, URL resolution. |

### Operations

| Page | What it covers |
|------|----------------|
| [**Configuration reference**](Configuration-reference) | Env / config grouped by area (IdP, OAuth, account, Socialite, provisioning). |
| [**Testing satellites**](Testing-satellites) | `InteractsWithFleetIdpPasswordReset`, PHPUnit env, `FleetIdp` in tests. |
| [**Troubleshooting**](Troubleshooting) | Common OAuth, forgot-password, Socialite, provisioning issues. |

## Source of truth (package repo)

Canonical markdown lives in **[`docs/wiki/`](https://github.com/shaferllc/fleet-idp-client/tree/main/docs/wiki)** on the main branch. Edit there (PRs), then sync to this wiki when you change operator docs.

## Sync `docs/wiki/` → GitHub Wiki

Wiki remote: **`https://github.com/shaferllc/fleet-idp-client.wiki.git`** (branch is usually **`master`**).

From your clone of the **package** repo:

```bash
WIKI=/path/to/fleet-idp-wiki-clone
DOCS=/path/to/fleet-idp-client/docs/wiki

cp "$DOCS/Home.md" "$WIKI/"
cp "$DOCS/_Sidebar.md" "$WIKI/"
cp "$DOCS/Fleet-Social-Login.md" "$WIKI/"
cp "$DOCS/Publishing-views-and-styling.md" "$WIKI/"
cp "$DOCS/Account-and-password.md" "$WIKI/"
cp "$DOCS/Configuration-reference.md" "$WIKI/"
cp "$DOCS/Provisioning-and-Fleet-lookup.md" "$WIKI/"
cp "$DOCS/Testing-satellites.md" "$WIKI/"
cp "$DOCS/Troubleshooting.md" "$WIKI/"

cd "$WIKI"
git add -A
git commit -m "Sync wiki from docs/wiki"
git push origin master
```

If your wiki uses **`main`**, run `git push origin main` instead.

**Note:** Do not copy **`docs/wiki/README.md`** into the wiki git repo — that file is for browsing the mirror inside the package tree only.

## Related repos

| Area | Repo / path |
|------|-------------|
| Identity provider | [shaferllc/fleet-auth](https://github.com/shaferllc/fleet-auth) |
| This Composer package | [shaferllc/fleet-idp-client](https://github.com/shaferllc/fleet-idp-client) |

The package **README** has install, CLI, and quick configuration; **this wiki** is the operator manual (especially **publishing and styling** account views).
