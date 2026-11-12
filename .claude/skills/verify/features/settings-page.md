# Settings page

The merchant configures the module at Stores > Configuration > Stape > Stape Conversion Tracking
(`/admin/admin/system_config/edit/section/stape_gtm/`), per default, website or store view scope.
Groups: **General**, **Data Layer**, **Transition Settings**, **Webhooks**. Fields show and hide by
dependency (the GTM ID only while **Active**; Custom Domain, Custom Loader and Cookie Keeper only
while the same-origin proxy is off).

## Sub-features

- `settings-general` Active, Web Google Tag Manager ID, Custom Domain, Custom Loader, Cookie Keeper.
- `settings-datalayer` ecommerce events, user data, Stape Suffix Enabled, Product Collection Limit.
- `settings-transition` Exclude Tax from Item Price, Use Display Currency for Amounts (restorable defaults).
- `settings-webhooks` the Webhooks group and its Send test webhook button (see order-webhooks).
- `settings-same-origin` the proxy toggle, path and API key (see same-origin-proxy).
- `settings-scope` a value saved at store view scope wins over the default on that store's storefront.
- `settings-loader-snippet` saving the General group regenerates the stored Custom Loader snippet through Stape's API.

## How to get to it (user POV)

- Merchant: admin menu Stores > Configuration > Stape > Stape Conversion Tracking; scope switcher
  at the top left; **Save Config**.

## Driving it with drive.mjs

Preconditions:

- Baseline store, doctor all `ok`.

- **Look.** `DIR=$($V/verify-env evidence settings-page)`;
  `node $V/drive.mjs admin admin/admin/system_config/edit/section/stape_gtm/ --out "$DIR"` → the
  page with all four group headings; on a fresh store only Transition Settings is expanded, so
  `admin.aria.yml` lists only its fields until a script opens the others.
- **Change and save.** Scratch script: `adminLogin(page)`, go to the section, open the group by its
  heading, fill a field by label (`page.getByLabel('Web Google Tag Manager ID')`), click
  **Save Config**, wait for `You saved the configuration.`, capture. Read back with
  `$V/verify-env magento config:show stape_gtm/general/container_id`, then capture a storefront page
  to show the effect. Restore the baseline value afterwards.
- **Proof** is the capture after save (message visible), the `config:show` output and the
  storefront capture showing the effect.

## Gotchas

- Group sections remember their open/closed state per admin user; open a group by clicking its
  heading only when its fields are hidden.
- The admin dashboard opens with Adobe usage-data and Payment Services modals that block clicks.
  `adminLogin()` then `page.goto()` straight to the config route skips the dashboard; a journey that
  must use the dashboard dismisses them first.
- Admin URL secret keys are off in the baseline (`admin/security/use_form_key 0`), so admin routes
  can be opened directly.
- Saving the section calls Stape's API for the loader snippet (`Observer/Admin/ConfigSave.php`);
  with no Custom Loader set it does nothing.
- Field labels carry the scope hint (`[STORE VIEW]`) in a separate element; `getByLabel` with the
  plain label still matches.
