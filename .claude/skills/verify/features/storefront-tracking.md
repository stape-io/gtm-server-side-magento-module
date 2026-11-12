# Storefront tracking events

While a shopper browses, carts and checks out, the module pushes ecommerce events to
`window.dataLayer`, named with the `_stape` suffix while **Stape Suffix Enabled** is on
(`view_item_stape`, `add_to_cart_stape`, ...). Each push is preceded by `{ecommerce: null}`. With
**Add user data to Data Layer events** on, a logged-in shopper's email, name and customer ID ride
along in `user_data`.

## Sub-features

- `view-collection` `view_collection_stape` on category, search, advanced search, wishlist and compare pages, at most **Product Collection Limit** items.
- `select-item` `select_item_stape` when a product tile in a list is clicked.
- `view-item` `view_item_stape` on a product page.
- `add-to-cart` `add_to_cart_stape` after Add to Cart, with `cart_state` (cart id, quantity, value, lines).
- `remove-from-cart` `remove_from_cart_stape` when a line leaves the cart (mini cart or cart page).
- `view-cart` `view_cart_stape` on `/checkout/cart/`.
- `begin-checkout` `begin_checkout_stape` on `/checkout/`.
- `payment-info` `payment_info_stape` when the payment step is submitted, with the billing/shipping person in `user_data`.
- `purchase` `purchase_stape` on the order success page.
- `user-data` `user_data` (email, first_name, last_name, customer_id) on events while logged in and the setting is on.
- `suffix-off` without the suffix, the same events go out with plain names (`view_item`, ...).
- `transition-settings` **Exclude Tax from Item Price** and **Use Display Currency for Amounts** change item prices and currency.

## How to get to it (user POV)

- Shopper: open a category (`/gear/bags.html`), search (`/catalogsearch/result/?q=bag`), a product
  (`/joust-duffle-bag.html`), press **Add to Cart**, open the mini cart and remove the line, open
  `/checkout/cart/`, **Proceed to Checkout**, fill the shipping step, choose **Check / Money order**,
  **Place Order**, land on `/checkout/onepage/success/`.
- Merchant: the Data Layer and Transition Settings groups on the settings page switch events, user
  data, suffix, limit and price rules.

## Driving it with drive.mjs

Preconditions:

- Baseline store, doctor all `ok`.
- `npm --prefix .claude/skills/verify/bin ci` has run once (Playwright), and `npx playwright install chromium`.

- **One page.** `DIR=$($V/verify-env evidence tracking-view-item)` then
  `node $V/drive.mjs storefront /joust-duffle-bag.html --out "$DIR"`. Prints the event names; the
  whole pushes are in `$DIR/storefront.datalayer.json`: `view_item_stape` with `ecommerce.items[0]`
  (`item_name` `Joust Duffle Bag`, `item_id` `"1"`, `item_sku` `24-MB01`, `item_category` `Bags`,
  `price` `"34.00"`, `currency` `EUR`: the sample store's base currency).
- **Category.** `node $V/drive.mjs storefront /gear/bags.html --out "$DIR"` → `view_collection_stape` with
  the listed products.
- **Add to cart.** A scratch script in `$DIR` (see `add-to-cart.mjs` below): `open()`, `page.goto('joust-duffle-bag.html')`,
  `settle(page)`, `addToCart(page, { qty: 2 })`, `capture(page, DIR, 'after-add', { pushes, scripts })`.
  Expect `add_to_cart_stape` with `ecommerce.value` `"68.00"`, `items[0].quantity` 2 and
  `cart_state.lines` holding the line.
- **Checkout to purchase.** Same script, continued: `page.goto('checkout/')`, fill the shipping form by
  label (Email Address, First Name, Last Name, Street Address, City, Country, State/Province,
  Zip/Postal Code, Phone Number), pick the Flat Rate method, **Next**, **Place Order**, wait for
  `/checkout/onepage/success/`, capture. Expect `begin_checkout_stape`, `payment_info_stape`, then
  `purchase_stape` with `ecommerce.transaction_id` equal to the order number on the page.
- **Proof** is the capture after the action (screenshot showing the page plus `datalayer.json`),
  asserted on the whole push, not just its name.

```js
// $DIR/add-to-cart.mjs — run with: node "$DIR/add-to-cart.mjs" "$DIR"
import { open, settle, addToCart, capture } from '<repo>/.claude/skills/verify/bin/drive.mjs';
const dir = process.argv[2];
const { browser, page, pushes, scripts } = await open();
await page.goto('joust-duffle-bag.html');
await settle(page);
await addToCart(page, { qty: 2 });
await capture(page, dir, 'after-add', { pushes, scripts });
await browser.close();
```

## Gotchas

- The store runs amd64 under emulation on Apple silicon: the first view of each page type compiles
  DI and static files and can take a minute. `drive.mjs` waits up to 3 minutes per navigation.
- `add_to_cart_stape` is pushed when Magento's cart section reloads after the add, not on the
  click; `addToCart()` waits for `customer/section/load`. Reading pushes earlier misses it.
- Configurable products (`/breathe-easy-tank.html`) need a size and colour chosen before Add to
  Cart; the event then carries `variation_id` and `item_variant`.
- Customer sections are cached in localStorage: a fresh `open()` per journey avoids a stale cart.
- `gtm.js` is answered with an empty script, so no tags fire; only the module's pushes are recorded.
