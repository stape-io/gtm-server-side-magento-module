# Stape GTM Server Side verification map

The maintained source for verifying what shoppers and merchants see of the `Stape_Gtm` module. Read
this index before driving the store, then use the matching feature file as the recipe. Terms (web
GTM ID, server container URL, Custom Loader, ...) are the ones in the repo's `GLOSSARY.md`.

## Baseline preconditions

- `.claude/skills/verify/bin/verify-env up` started the store from this checkout, and
  `verify-env doctor` prints only `ok` lines. Storefront, admin and sink URLs are in `.verify/env.json`.
- The store is Magento 2.4.8-p5 Open Source, Luma theme, with the sample-data catalog (Gear, Men,
  Women; simple products such as `/joust-duffle-bag.html`, configurables such as
  `/breathe-easy-tank.html`). Its database lives in the container: `verify-env down` throws it away.
- The baseline module settings: module **Active** on, **Web Google Tag Manager ID** `GTM-VERIFY1`,
  ecommerce events on, user data on, `_stape` suffix on (default), webhooks **off**, server
  container URL `http://sink:8080/hook/default`. Admin user `verify`, password in `.verify/env.json`.
- No customer accounts exist; journeys that need a logged-in shopper register one through
  `/customer/account/create/`.
- Magento reaches the webhook sink as `http://sink:8080/hook/<label>`; the host reads it with
  `verify-env sink 'label=<label>'`.
- Never drive a store `verify-env` did not start from this checkout (doctor's first line).

## Driving conventions

- Start from the baseline; a recipe that changes settings says so and restores them.
- Settings changes for a journey go through the admin page (Stores > Configuration > Stape > Stape
  Conversion Tracking). `verify-env magento config:set` is setup, never the thing being proven.
- Storefront handles are Luma's ARIA roles and names (button `Add to Cart`, spinbutton `Qty`,
  button `Place Order`); admin fields by their label (`Web Google Tag Manager ID`, `Enable Webhooks`).
- Commands below are literal and run from the repo root. `V=.claude/skills/verify/bin`.
- After editing module code: `verify-env refresh`, then drive. A store started before the edit
  otherwise serves cached layout, DI and static JS.

## Proof and skip reporting

- Proof is a `drive.mjs` capture (`.png`, `.aria.yml`, `.datalayer.json`, `.scripts.json`,
  `.cookies.json`) in an evidence directory from `verify-env evidence <feature>-<entry-point>`.
- Tracking events are proven by the whole recorded push, not by its name.
- Order webhooks are proven by the sink's recorded request (method, `x-stape-app-version` header,
  JSON body), saved next to the capture: `verify-env sink 'label=<label>' > "$DIR/sink.json"`.
- A saved setting is proven by the admin page after save and read back with
  `verify-env magento config:show <path>`, plus its effect on the storefront or webhook.
- Report a journey that cannot run with the command, the unmet precondition and which sub-feature
  stays unverified. Never report one entry point as verified through another.

## Features

- [GTM loader](./gtm-loader.md): how the storefront loads the web GTM container: googletagmanager.com, custom domain, Custom Loader, Cookie Keeper.
- [Storefront tracking events](./storefront-tracking.md): the `*_stape` dataLayer pushes while browsing, carting and checking out.
- [Order webhooks](./order-webhooks.md): purchase and refund webhooks to the server container URL, and "Send test webhook".
- [Settings page](./settings-page.md): Stores > Configuration > Stape > Stape Conversion Tracking, its validation and scopes.
- [Same-origin proxy](./same-origin-proxy.md): GTM and sGTM traffic served from the store's own domain under a proxy path.
