---
name: verify
description: Launch a real Magento 2.4.8 store (Luma, sample data) running the Stape_Gtm module from this checkout, drive its storefront, admin settings page and order webhooks the way a shopper or merchant does, and keep the evidence. Use to prove a module change works in a running store, to reproduce a bug before fixing it, or whenever a claim about dataLayer events, the GTM loader, webhooks or the settings page needs a run instead of a reading.
---

# Verify Stape GTM Server Side in a running Magento

The module has three surfaces, all driven here:

- **Storefront**: the GTM snippet in `<head>` and the `*_stape` dataLayer pushes (Luma theme).
- **Admin**: Stores > Configuration > Stape > Stape Conversion Tracking
  (`/admin/admin/system_config/edit/section/stape_gtm/`), including Send test webhook and the
  same-origin proxy's Test connection.
- **Order webhooks**: server-to-server POSTs to the server container URL; a local webhook sink
  records them.

The repo is only the module; the host is the community CI image
`michielgerritsen/magento-project-community-edition:php83-fpm-magento2.4.8-p5-sample-data` (Magento,
MySQL, Elasticsearch and nginx in one amd64 container, pre-installed with sample data). The checkout
is mounted read-only and installed through a Composer path repository, so the store runs the working
tree. Pick the feature in [`features/README.md`](features/README.md) and follow its recipe.

All commands run from the repo root. `V=.claude/skills/verify/bin` below.

## Launch

```sh
npm --prefix .claude/skills/verify/bin ci         # once per checkout: Playwright for drive.mjs
.claude/skills/verify/bin/verify-env up
```

- Starts two containers on their own Docker network, named and ported from this checkout's path
  (`stape-verify-<hash>`, ports printed): the store, and `sink` (`node:22-alpine` running
  `bin/sink.mjs`). Worktrees never collide.
- Then `composer require stape/gtm-server-side-magento-module:*` from the mount (fetches
  `jeremykendall/php-domain-parser` from Packagist), `setup:upgrade`, the baseline settings
  (module on, `GTM-VERIFY1`, ecommerce and user data on, webhooks off, admin URL keys off), and an
  admin user `verify` with a random password recorded in `.verify/env.json`.
- About 2 minutes on Apple silicon with the image cached (emulated amd64); the first pull is ~1.4 GB.
  Run it in the background.
- **Ready** = it ends by printing `doctor` with every line `ok`; `up` exits non-zero otherwise.
- Refuses to start when `.verify/env.json` exists, when containers with its names exist, or when a
  port is taken (`VERIFY_PORT` / `VERIFY_SINK_PORT` override).
- After editing module PHP, layout, templates or JS: `verify-env refresh` (clears caches, generated
  code and the module's static files). After `etc/db_schema*`, `etc/module.xml` or `composer.json`
  changes: `verify-env down && verify-env up`.

## Doctor

```sh
.claude/skills/verify/bin/verify-env doctor
```

Read-only. Prints the URLs, then `ok`/`FAIL` for: the store container runs and carries this
checkout's label, the port maps to it, the checkout is the mounted source, the sink answers on the
host and from Magento (`http://sink:8080`), `Stape_Gtm` is enabled and symlinked to the mount, the
storefront renders `Stape_Gtm/js/datalayer`, and the `verify` admin user exists. It also prints the
module version from `composer.json` and the Magento version. Exit 1 on any failure. Run it first, and
before trusting any result. Never drive a store whose doctor fails or that `verify-env` did not start
(`.verify/env.json` is the ownership record).

## Drive

`bin/drive.mjs` (Playwright, Chromium) is the harness; there is no test suite in this repo.

```sh
DIR=$(.claude/skills/verify/bin/verify-env evidence tracking-view-item)
node .claude/skills/verify/bin/drive.mjs storefront /joust-duffle-bag.html --out "$DIR"
node .claude/skills/verify/bin/drive.mjs admin admin/admin/system_config/edit/section/stape_gtm/ --out "$DIR"
```

Writes `<mode>.png` (full page), `<mode>.aria.yml`, `<mode>.datalayer.json` (every push, in order,
with its page URL), `<mode>.scripts.json` (script requests) and `<mode>.cookies.json`, and prints the
event names pushed. gtm.js from googletagmanager.com is answered with an empty script.

For a journey with several steps, write a scratch script in the evidence directory that imports
`open`, `settle`, `adminLogin`, `addToCart` and `capture` from `drive.mjs` (an example is in
`features/storefront-tracking.md`) and run it with `node "$DIR/<script>.mjs" "$DIR"`. Use Luma's
ARIA roles and names (`getByRole('button', { name: 'Add to Cart' })`) and admin field labels
(`getByLabel('Web Google Tag Manager ID')`), never coordinates.

Side-channel reads (second views of state, never the action itself):

```sh
.claude/skills/verify/bin/verify-env magento config:show stape_gtm          # stored settings
.claude/skills/verify/bin/verify-env sink 'label=<label>'                   # recorded webhooks (JSON)
.claude/skills/verify/bin/verify-env sql 'select increment_id, state from sales_order order by entity_id desc limit 3'
.claude/skills/verify/bin/verify-env logs                                   # exception.log / system.log tails
```

## Evidence

Everything goes under `.verify/runs/` (gitignored); `verify-env down` never touches it.

- Exercise the real user path: storefront clicks and admin form saves. `verify-env magento
  config:set` and SQL are setup, never the thing being proven.
- Capture the action and the resulting state: a capture before and after, plus the dataLayer pushes.
  A final screenshot alone proves nothing.
- Check side effects next to what's visible: the sink's recorded request for a webhook,
  `config:show` after a settings save, cookies for Cookie Keeper.
- Assert whole events (the full push JSON): a field the module starts or stops sending is a
  behaviour change.
- The only stand-ins: gtm.js answered empty, the sink in place of the merchant's server container.
  Stape's API (Custom Loader snippet) and Stape containers (same-origin proxy) are real and need real
  identifiers or keys from env vars; without them those journeys are unreachable, not verified.
- Report the doctor output, the Magento version, the commands and the evidence path.

## Cleanup

```sh
.claude/skills/verify/bin/verify-env down
```

Removes exactly the two containers and the network `up` recorded (it checks the label holding this
checkout's path, never matches by name alone), with their data, and removes `.verify/env.json`.
Evidence stays. Run it after every run, failed ones included. The sink's recordings die with it:
save them first (`verify-env sink > "$DIR/sink.json"`).

## Secrets

- The admin password is generated per `up` and lives only in `.verify/env.json` (gitignored).
- A same-origin proxy journey reads the Stape container API key from
  `STAPE_VERIFY_SAME_ORIGIN_API_KEY`; never write it to a file in the repo or the evidence.
- Production stores and live Stape containers that serve real traffic are never a target.

## Gotchas

- The image is amd64 only. On Apple silicon it runs emulated: the first view of each page type
  takes 10–60 s while developer mode compiles. `drive.mjs` allows 3 minutes per navigation.
- The store's base currency is EUR; sample prices are in EUR.
- Hyvä storefronts (`view/frontend/templates/hyva`, `web/js/hyva`) are not covered: the image has
  Luma only, and Hyvä needs a licence.
- The image's own `exampleuser` admin account has an unknown password; use `verify`.
- `node_modules` for `drive.mjs` lives in `bin/` and pins Playwright 1.64.0 (Chromium 1248). If its
  browser is missing, `npx --prefix .claude/skills/verify/bin playwright install chromium`; never run
  two installs at once, they block each other on the cache lock.
