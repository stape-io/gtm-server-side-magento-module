# Stape GTM Server Side for Magento 2

A Magento 2 module that puts a merchant's store on server-side tagging: it loads Google Tag Manager, pushes ecommerce events to the data layer and sends order webhooks to the merchant's server GTM container.

## Language

### Containers and identifiers

**Web GTM ID**:
The ID of the merchant's web Google Tag Manager container, in the form `GTM-XXXXXX`.
_Avoid_: container ID, GTM container ID

**Server container URL**:
The URL of the merchant's server GTM container, from which the store loads the GTM script and to which it sends webhooks.
_Avoid_: custom domain, GTM container URL, sGTM URL

**Stape container identifier**:
The short identifier Stape assigns to a server container hosted on Stape, used to address that container's Custom Loader.
_Avoid_: custom loader, container ID

**Stape container API key**:
The secret key of a Stape-hosted server container, used for the Same-origin proxy.
_Avoid_: container identifier, API token

### Stape power-ups

**Custom Loader**:
The Stape power-up that serves the web GTM script from the merchant's server container instead of googletagmanager.com.
_Avoid_: custom GTM loader, loader (for the identifier)
