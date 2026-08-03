# Stape GTM Server Side Magento Module

If you have your store on Magento, [Stape's GTM Server Side extension for Magento 2](https://commercemarketplace.adobe.com/stape-gtm-server-side-magento-module.html) can simplify the process and open up the world of server-side tagging. 

## The list of e-commerce events:
- Collection view
- View Item
- Add to cart
- Begin checkout
- Add payment info
- Purchase

## User data:

- Email
- First name
- Last name
- Customer ID

## Same-origin proxy (Beta)

Serve the GTM loader and all sGTM traffic from your own store domain: requests to a
first-party path (e.g. `/gtm/`) are transparently forwarded by Magento to your Stape
sGTM container — no DNS, CDN or reverse-proxy setup required.

Enable it under **Stores → Configuration → Stape → Stape Conversion Tracking → General →
Same-origin proxy (Beta)** and set:

- **Proxy path** — must start with `/` (e.g. `/gtm`). Any query string or fragment is dropped, duplicate slashes are collapsed and a trailing slash is removed, so `/gtm/` is stored as `/gtm`.

- **Container API key** — the full Stape container API key (not the container identifier).

After saving, use the **Test connection** button to verify the path is not shadowed by an
existing route, URL rewrite, redirect, or CDN/web-server rule.

Notes:

- If your nginx/Apache serves `.js` (or other static extensions) directly, requests under
  the proxy path with those extensions bypass PHP. The module works around this with the
  `.load` extension, but you can also route the proxy path to Magento explicitly, e.g. nginx:

  ```nginx
  location ^~ /gtm/ { try_files $uri /index.php$is_args$args; }
  ```

- Proxy responses keep the cache lifetime your Stape container sets, but are always marked `private`, so browsers may reuse them while Magento's full page cache and any standards-compliant shared cache pass them through. Add an explicit rule if a cache in front of Magento is configured to ignore origin cache headers, e.g. Varnish VCL:

  ```vcl
  sub vcl_recv {
      if (req.url ~ "^/gtm/") {
          return (pass);
      }
  }
  ```

  On a CDN, create an equivalent bypass rule for the same path prefix. Replace `/gtm/` with the configured proxy path in both cases.

- While the same-origin proxy is fully configured, Cookie Keeper is superseded by
  first-party delivery and treated as disabled.

## Useful links:
 
- https://stape.io/blog/server-side-gtm-extension-for-magento-2
- https://stape.io/blog/facebook-conversion-api-for-magento
- https://stape.io/blog/server-side-google-analytics-4-for-magento 
