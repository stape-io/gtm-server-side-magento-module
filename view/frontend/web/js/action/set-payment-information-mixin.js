define([
    'mage/utils/wrapper',
    'underscore',
    'Magento_Checkout/js/model/quote',
    'Magento_Customer/js/customer-data'
], function(wrapper, _, quote, customerData) {
    'use strict';

    /**
     * Format a monetary value as a canonical fixed 2-decimal string
     * (e.g. 10 => "10.00"), locale-independent so no comma/grouping leaks in.
     *
     * Returns undefined for null/empty/non-numeric input so a missing total is
     * omitted from the payload rather than emitted as a fake "0.00". A genuine
     * zero still formats as "0.00".
     *
     * @param {*} v
     * @returns {String|undefined}
     */
    function toMoney(v) {
        if (v === null || v === undefined || v === '' || isNaN(Number(v))) {
            return undefined;
        }
        return Number(v).toFixed(2);
    }

    /**
     * Parse item variant SKU
     *
     * @param itemSku
     * @param baseSku
     * @returns {string|null}
     */
    function getItemVariantSku(itemSku, baseSku) {
        return (itemSku !== baseSku && itemSku.indexOf(baseSku) === 0)
            ? itemSku.substring(baseSku.length).replace(/^[-\s]+/, '')
            : null;
    }

    /**
     * Prepare quote items for data layer
     *
     * @returns {*}
     */
    function prepareItems() {
        const cartData = customerData.get('cart')();
        return quote.getItems().map(function(itemDetails) {
            const cartItem = _.find(cartData.items, function(cartItem) {
                return cartItem.item_id === itemDetails.item_id;
            });

            const baseSku = cartItem.product_sku;
            const itemSku = itemDetails.sku;

            return {
                'item_name': itemDetails.name,
                'item_id': itemDetails.product_id,
                'item_sku': baseSku,
                'item_category': cartItem.category,
                'price': toMoney(itemDetails.base_price),
                'quantity': parseInt(itemDetails?.qty),
                'variation_id': cartItem.child_product_id ? cartItem.child_product_id : undefined,
                'item_variant': cartItem.child_product_sku ? cartItem.child_product_sku : getItemVariantSku(itemSku, baseSku)
            }
        });
    }

    function getCartState() {
        const cartData = customerData.get('cart')();
        return {
            cart_id: cartData?.stape_cart_id,
            cart_quantity: quote?.totals()?.items_qty,
            currency: window.checkoutConfig?.quoteData?.quote_currency_code,
            cart_value: toMoney(quote?.totals().grand_total),
            lines: quote?.getItems()?.map(item => {
                const cartItem = _.find(cartData.items, function(cartItem) {
                    return cartItem.item_id === item.item_id;
                });

                const baseSku = cartItem.product_sku;
                const itemSku = item.sku;

                return {
                    'item_variant': cartItem.child_product_sku ? cartItem.child_product_sku : getItemVariantSku(itemSku, baseSku),
                    'item_id': cartItem.product_id,
                    'item_name': item.name,
                    'item_sku': baseSku,
                    'quantity': item.qty,
                    'line_total_price': toMoney(item?.row_total_incl_tax),
                    'price': toMoney(item.price),
                }
            })
        }
    }

    /**
     * Customizing logic to push info into datalayer
     */
    return function(setPaymentInformation) {
        return wrapper.wrap(setPaymentInformation, function(originalAction, messageContainer, paymentData, skipBilling) {
            return originalAction(messageContainer, paymentData, skipBilling).then(function(response) {
                let address = quote.billingAddress();
                const customer = customerData.get('customer')();
                if (!quote.isVirtual()) {
                    address = Object.assign({...address}, quote.shippingAddress());
                }
                window.dataLayer.push({ecommerce: null});
                window.dataLayer.push({
                    event: 'payment_info' + window?.dataLayerConfig?.stapeEventSuffix || '',
                    ecomm_pagetype: 'basket',
                    user_data: {
                        first_name: address.firstname,
                        last_name: address.lastname,
                        email: address.email || customer?.email || quote.guestEmail,
                        phone: address.telephone,
                        country: address.countryId,
                        region: address.region,
                        city: address.city,
                        street: address.street.join(', '),
                        zip: address.postcode,
                        customer_id: quote.customer_id,
                    },
                    ecommerce: {
                        cart_state: getCartState(),
                        currency: window.checkoutConfig?.quoteData?.quote_currency_code,
                        cart_total: toMoney(quote.totals().grand_total),
                        cart_quantity: quote.totals().items_qty,
                        value: toMoney(quote.totals()?.grand_total),
                        items: prepareItems()
                    }
                })
                return response;
            });
        });
    }
});
