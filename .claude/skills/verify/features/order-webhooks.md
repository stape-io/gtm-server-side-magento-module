# Order webhooks

With **Enable Webhooks** on and a **GTM server container URL** set, Magento POSTs JSON to the server
container: `purchase_stape_webhook` once per order (when the order reaches **Required Order State For
Purchase**), and `refund_stape_webhook` on a credit memo or an order cancel. **Send test webhook** on
the settings page posts a sample purchase. Each request carries the `x-stape-app-version` header.

## Sub-features

- `webhook-purchase` one `purchase_stape_webhook` per order with `user_data`, `ecommerce` (transaction id, value, items), `payment_type`, `shipping_tier` and the shopper's cookies.
- `webhook-purchase-state` sent only once the order reaches the required state (e.g. after invoicing), not before.
- `webhook-refund` `refund_stape_webhook` on a credit memo (full or partial).
- `webhook-cancel` `refund_stape_webhook` when the order is cancelled.
- `webhook-test` **Send test webhook** posts a sample purchase and reports success or failure in the admin.
- `webhook-switches` **Enable Webhooks**, **Purchase Webhook**, **Refund Webhook** gate each one; an empty URL sends nothing and logs `[STAPE WEBHOOK ...]`.
- `webhook-failure` a failing or hanging server container breaks neither checkout nor the admin action.

## How to get to it (user POV)

- Merchant: settings page > **Webhooks** group: **Enable Webhooks** Yes, **GTM server container URL**,
  **Purchase Webhook**, **Refund Webhook**, **Required Order State For Purchase**; Save Config; then
  **Send test webhook**.
- Shopper: place an order (see storefront-tracking's checkout recipe).
- Merchant: Sales > Orders > the order > **Invoice**, **Credit Memo** or **Cancel**.

## Driving it with drive.mjs

Preconditions:

- Baseline store (webhooks off, URL `http://sink:8080/hook/default`), doctor all `ok`, including
  `webhook sink up`.

- **Switch on.** Scratch script: `adminLogin(page)`, `page.goto('admin/admin/system_config/edit/section/stape_gtm/')`,
  open the **Webhooks** group, set **Enable Webhooks** and **Purchase Webhook** to Yes, set
  **GTM server container URL** to `http://sink:8080/hook/<label>`, **Save Config**, capture. Read
  back: `$V/verify-env magento config:show stape_gtm/webhooks`.
- **Test webhook.** On the same page, **Send test webhook** → admin message; then
  `$V/verify-env sink 'label=<label>' > "$DIR/sink.json"` holds one POST with `event`
  `purchase_stape_webhook` and the sample `John Doe` user data.
- **Purchase.** Place an order as a shopper, invoice it if the required state demands it, then read
  the sink: the request's `json.ecommerce.transaction_id` is the order number.
- **Refund.** Sales > Orders > order > Credit Memo > **Refund Offline**, then read the sink for
  `refund_stape_webhook`.
- **Proof** is `sink.json` (method `POST`, `x-stape-app-version` equal to `composer.json`'s version,
  whole JSON body) next to the admin or storefront capture of the action.

## Gotchas

- Magento sends to `http://sink:8080/...` (the container network alias); the host reads the sink on
  the port doctor prints. `localhost` in the URL reaches the Magento container itself.
- `/hook/<label>/status/<code>` and `/hook/<label>/delay/<seconds>` make the sink answer like a
  failing or hanging server container.
- The sink keeps requests in memory: `verify-env down` loses them. Save them to the evidence
  directory first.
- `x-stape-app-version` comes from a constant in `Model/Api/Client.php`, not from `composer.json`;
  a mismatch after a version bump is a real finding.
- Webhook errors only reach `var/log` (`verify-env logs`), never the shopper.
