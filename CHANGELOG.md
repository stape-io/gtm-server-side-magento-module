# Changelog

v.1.0.36
- added admin configuration option to control purchase webhook;
- added payment_type param for purchase webhook;
- added shipping_tier param for purchase webhook;

v1.0.35
- fixed PHP7.4 backward compatibility;
- fixed CSP issue on checkout;

v1.0.34
- improved SKU tracking

v1.0.33
- added cart state to datalayer events;
- extended cookies list;

v1.0.32
- datalayer event value param added;

v1.0.31
- renamed module in admin panel;
- added select_item event;
- added item_variant param to datalayer;

v1.0.30
- added module version header;

v1.0.29
- img-src CSP rule added;

v1.0.28
- added PHP 8.4 compatibility;
- fixed code style;

v1.0.27
- added option to enable/disable '_stape' suffix in Datalayer events;

v1.0.26
- removed unneeded CSP rules
- added option to configure collection size for datalayer

v1.0.24
- added caching of cookie domain when generating _sbp cookie
- Fixed issue with the overridden price formatter pattern
- Fixed billing address overwritten by shipping address issue

v1.0.23
- added logic to fetch add to cart info and send the event if only productIds array is available

v1.0.22
- fixed duplicate address creation for logged in customer on checkout

v1.0.21
- removed Stape analytics option as no longer needed

v1.0.20
- removed usage of md5 hash

v1.0.19
- Implemented custom GTM loader generation logic with prefix as well as container id

v1.0.18
- Fixed potential issue with sending multiple purchase webhook events.

v1.0.17
- Hyva theme compatibility added.

v1.0.16
- acl.xml added.

v1.0.15
- fixed logic to trim GTM- only when custom loader and custom domain are populated.

v1.0.14
- fixed xml layouts.
- changed class methods visibility from private to public.

v1.0.13
- fixed broken url generation logic.

v1.0.12
- added quote_id param to purchase event and webhook.

v1.0.11
- Update snippets with new param names and format.

v1.0.10
- Fixed item price for purchase_stape event to include tax.

v1.0.9
- Fixed issue with cookie being re-generated on every page load.

v1.0.2
- Fixed issue with main snippet not showing when cookie keeper enabled.

v1.0.1
- Set _sbp cookie logic added, unneeded functionality cleaned.

v1.0.0
- Main functionality implemented.
