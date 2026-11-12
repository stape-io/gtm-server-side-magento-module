# GTM loader

With the module **Active** and a **Web Google Tag Manager ID** set, every storefront page carries the
GTM snippet in `<head>`. It loads `gtm.js` from googletagmanager.com, from the merchant's **Custom
Domain** (the server container URL), or through Stape's **Custom Loader** script when a Stape
container identifier is set. **Cookie Keeper** adds the `_sbp` first-party cookie that the Custom
Loader reads to restore the visitor's identity in Safari.

## Sub-features

- `loader-default` snippet requesting `https://www.googletagmanager.com/gtm.js?id=<web GTM ID>`.
- `loader-custom-domain` the same snippet, served from **Custom Domain**.
- `loader-custom-loader` the Stape Custom Loader snippet (`<domain>/<identifier>.js?...`) when **Custom Loader** is set.
- `cookie-keeper` the `_sbp` cookie set on storefront responses while Cookie Keeper is on; removed when it is turned off.
- `loader-off` no snippet and no dataLayer bootstrap while the module is inactive or the ID is empty.

## How to get to it (user POV)

- Merchant: Stores > Configuration > Stape > Stape Conversion Tracking > General: **Active**,
  **Web Google Tag Manager ID**, **Custom Domain**, **Custom Loader**, **Cookie Keeper**; Save Config.
- Shopper: open any storefront page; the browser requests the loader script.

## Driving it with drive.mjs

Preconditions:

- Baseline store, doctor all `ok` (baseline ID `GTM-VERIFY1`, no custom domain or loader).

- **Default loader.** `DIR=$($V/verify-env evidence loader-default)`;
  `node $V/drive.mjs storefront / --out "$DIR"` → `$DIR/storefront.scripts.json` contains
  `https://www.googletagmanager.com/gtm.js?id=GTM-VERIFY1`, and the first push is `gtm.js`.
- **Custom domain / Custom Loader / Cookie Keeper.** Change the fields on the settings page
  (`node $V/drive.mjs admin admin/admin/system_config/edit/section/stape_gtm/ --out "$DIR"` shows it;
  a scratch script fills `Custom Domain` with `https://sgtm.example.test` and saves), then capture
  `/` again: `scripts.json` shows the request to the custom domain, `cookies.json` the `_sbp` cookie.
  Restore the fields to empty / No afterwards.
- **Module off.** Set **Active** to No on the settings page and capture `/`: no `gtm.js` request,
  no `Stape_Gtm/js/datalayer` in the page.
- **Proof** is `scripts.json` (the loader URL requested) plus `cookies.json` for Cookie Keeper and
  the screenshot of the saved settings page.

## Gotchas

- The capture answers only googletagmanager.com with an empty script. A custom domain or Custom
  Loader URL is not stubbed: the request appears in `scripts.json` and fails (no such host), which is
  the expected result here.
- Custom Loader and Cookie Keeper are hidden while the same-origin proxy is on; they also stay
  inactive while it is fully configured.
- Saving the General group asks Stape's API (`api.app.stape.io`) to generate the loader snippet for
  the configured identifier and stores it (`stape_gtm/general/gtm_snippet`); without a reachable
  Stape API or with a made-up identifier the stored snippet is empty and the default loader renders.
  A real Custom Loader journey needs a real Stape container identifier.
- Full page cache is on: after a settings save the admin flushes the affected caches, but a
  `config:set` from the CLI needs `verify-env magento cache:flush`.
