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
     * Unit price of a cart line, resolved server side by Stape so the tax and
     * currency settings are honoured in one place.
     *
     * Falls back to the checkout quote item, picking the base or display column
     * according to the configured currency, for the window where a cached cart
     * section predates this feature.
     *
     * @param {Object} cartItem customer-data cart line
     * @param {Object} quoteItem checkout quote item
     * @returns {Number|undefined}
     */
    function linePrice(cartItem, quoteItem) {
        if (cartItem?.stape_price !== undefined && cartItem?.stape_price !== null) {
            return cartItem.stape_price;
        }

        return useDisplayCurrency() ? quoteItem?.converted_price : quoteItem?.price;
    }

    /**
     * Line total of a cart line, see linePrice for the fallback rationale.
     *
     * @param {Object} cartItem customer-data cart line
     * @param {Object} quoteItem checkout quote item
     * @returns {Number|undefined}
     */
    function lineTotal(cartItem, quoteItem) {
        if (cartItem?.stape_line_total !== undefined && cartItem?.stape_line_total !== null) {
            return cartItem.stape_line_total;
        }

        return useDisplayCurrency()
            ? quoteItem?.row_total_incl_tax
            : quoteItem?.base_row_total_incl_tax;
    }

    /**
     * Check if amounts are reported in the display currency
     *
     * @returns {Boolean}
     */
    function useDisplayCurrency() {
        return window?.dataLayerConfig?.useDisplayCurrency === true;
    }

    /**
     * Currency code the amounts are expressed in
     *
     * @param {Object} cartData
     * @returns {String|undefined}
     */
    function currencyCode(cartData) {
        return cartData?.stape_currency || (useDisplayCurrency()
            ? window.checkoutConfig?.quoteData?.quote_currency_code
            : window.checkoutConfig?.quoteData?.base_currency_code);
    }

    /**
     * Grand total of the quote in the reported currency
     *
     * @param {Object} cartData
     * @returns {Number|undefined}
     */
    function cartValue(cartData) {
        if (cartData?.stape_cart_value !== undefined && cartData?.stape_cart_value !== null) {
            return cartData.stape_cart_value;
        }

        return useDisplayCurrency()
            ? quote?.totals()?.grand_total
            : quote?.totals()?.base_grand_total;
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
                'price': toMoney(linePrice(cartItem, itemDetails)),
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
            currency: currencyCode(cartData),
            cart_value: toMoney(cartValue(cartData)),
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
                    'line_total_price': toMoney(lineTotal(cartItem, item)),
                    'price': toMoney(linePrice(cartItem, item)),
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
                        currency: currencyCode(customerData.get('cart')()),
                        cart_total: toMoney(cartValue(customerData.get('cart')())),
                        cart_quantity: quote.totals().items_qty,
                        value: toMoney(cartValue(customerData.get('cart')())),
                        items: prepareItems()
                    }
                })
                return response;
            });
        });
    }
});
