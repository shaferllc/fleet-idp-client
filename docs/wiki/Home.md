# Fleet identity wiki

Reference for **Fleet Auth** (Passport IdP) and **`shaferllc/fleet-idp-client`** (satellite apps: Waypost, Fleet Console, etc.).

## Pages

| Page | What it covers |
|------|----------------|
| [Fleet Social Login (GitHub / Google)](Fleet-Social-Login) | Third-party OAuth on client apps, per-client Passport toggles, policy API, env vars, troubleshooting |

## Source files in this repository

Canonical markdown lives in the package repo under **[`docs/wiki/`](https://github.com/shaferllc/fleet-idp-client/tree/main/docs/wiki)**. That folder is the same content as this GitHub Wiki.

## Enable the GitHub Wiki (if `wiki` is empty)

GitHub does **not** expose `https://github.com/OWNER/REPO.wiki.git` until **Wikis** are turned on and at least one page exists.

1. Open **[github.com/shaferllc/fleet-idp-client/settings](https://github.com/shaferllc/fleet-idp-client/settings)** → **General** → **Features** → enable **Wikis** → **Save**.
2. Either click **Wiki** → create a **Home** page once, **or** push from git (below).

### Push these files into the wiki git repo

From a machine with GitHub access:

```bash
git clone https://github.com/shaferllc/fleet-idp-client.wiki.git fleet-idp-wiki
cd fleet-idp-wiki
# copy from a checkout of the package:
cp /path/to/fleet-idp-client/docs/wiki/Home.md .
cp /path/to/fleet-idp-client/docs/wiki/Fleet-Social-Login.md .
cp /path/to/fleet-idp-client/docs/wiki/_Sidebar.md .
git add -A
git commit -m "Add Fleet identity wiki"
git push origin master
```

If the default branch is `main` instead of `master`, use `git push origin main`. After the first successful push, pages appear at **[github.com/shaferllc/fleet-idp-client/wiki](https://github.com/shaferllc/fleet-idp-client/wiki)**.

## Related repos

| Area | Repo / path |
|------|-------------|
| Identity provider | [shaferllc/fleet-auth](https://github.com/shaferllc/fleet-auth) |
| This Composer package | [shaferllc/fleet-idp-client](https://github.com/shaferllc/fleet-idp-client) |

The package **README** has shorter install and Socialite notes; this wiki goes deeper for operators.
