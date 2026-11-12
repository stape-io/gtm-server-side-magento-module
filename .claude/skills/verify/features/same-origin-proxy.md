# Same-origin proxy

With **Same-origin proxy (Beta)** on, a **Proxy path** (e.g. `/gtm`) and a **Container API key**,
Magento forwards every request under the proxy path to the merchant's Stape server container
(`https://<identifier>.<region>.stape.<tld>`, derived from the key), so the GTM loader and all sGTM
traffic come from the store's own domain. The loader is requested with the `.load` extension so web
servers that serve `.js` directly still route it to PHP. **Test connection** checks that the path is
not shadowed by a route, rewrite or redirect.

## Sub-features

- `proxy-settings` toggle, path normalisation (`/gtm/` stored as `/gtm`, query and fragment dropped), API key field.
- `proxy-test-connection` the admin **Test connection** button.
- `proxy-loader` the storefront snippet loads `<store>/<path>/<...>.load` instead of googletagmanager.com.
- `proxy-forward` requests under the path reach the container with `x-stape-host` and the shopper's cookies; responses come back marked `private`.
- `proxy-service-worker` the container's service worker registration is rewritten to `.load`.
- `proxy-supersedes` Custom Domain, Custom Loader and Cookie Keeper are hidden and inactive while the proxy is configured.

## How to get to it (user POV)

- Merchant: settings page > General > **Same-origin proxy (Beta)** Yes, **Proxy path**, **Container
  API key**, Save Config, **Test connection**.
- Shopper: any storefront page; the browser fetches the loader from the store's own domain.

## Driving it with drive.mjs

Preconditions:

- Baseline store, doctor all `ok`.
- For `proxy-loader`, `proxy-forward` and `proxy-service-worker`: a real Stape container API key in
  `STAPE_VERIFY_SAME_ORIGIN_API_KEY` (never committed), and the container's sGTM reachable from the
  Docker host.

- **Settings.** Scratch script: `adminLogin(page)`, settings page, set **Same-origin proxy (Beta)**
  Yes, **Proxy path** `/gtm/`, **Container API key** from the env var, Save Config, capture; read
  back `$V/verify-env magento config:show stape_gtm/general/same_origin_path` → `/gtm`.
- **Test connection.** Click **Test connection**, capture the result message.
- **Loader and forward.** `node $V/drive.mjs storefront / --out "$DIR"` → `storefront.scripts.json`
  holds `http://localhost:<port>/gtm/...load`; `curl -si http://localhost:<port>/gtm/healthy` answers
  from the container with `Cache-Control` containing `private`.
- **Proof** is the saved settings capture, the storefront `scripts.json` and the proxied response
  headers. Without a key, report `proxy-loader`, `proxy-forward` and `proxy-service-worker` as
  unreachable (no container API key), never as verified through the settings alone.

## Gotchas

- Not driven yet when this map was seeded: no Stape container key was available. The first run
  with a key should confirm these recipes and fix them.
- The store is plain HTTP on localhost; the container derives `x-stape-host` from the request host,
  so the container sees `localhost:<port>`.
- A path that matches an existing Magento route (e.g. `/checkout`) is shadowed; Test connection
  should report it.
