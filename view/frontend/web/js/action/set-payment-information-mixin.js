define([
    'mage/utils/wrapper',
    'underscore',
    'Magento_Checkout/js/model/quote',
    'Magento_Customer/js/customer-data',
    'Magento_Catalog/js/price-utils'
], function(wrapper, _, quote, customerData, priceUtils) {
    'use strict';

    /**
     * Prepare quote items for data layer
     *
     * @returns {*}
     */
    function prepareItems() {
        const cartData = customerData.get('cart')();
        const priceFormat = Object.assign({...quote.getPriceFormat()}, {'pattern': '%s'});
        const useSkuAsId = window?.dataLayerConfig?.useSkuAsItemId || false;
        return quote.getItems().map(function(itemDetails) {
            const cartItem = _.find(cartData.items, function(cartItem) {
                return cartItem.item_id === itemDetails.item_id;
            });

            const baseSku = cartItem.product_sku;
            const itemSku = itemDetails.sku;
            const itemVariant = (itemSku !== baseSku && itemSku.indexOf(baseSku) === 0)
                ? itemSku.substring(baseSku.length).replace(/^[-\s]+/, '')
                : null;

            return {
                'item_name': itemDetails.name,
                'item_id': useSkuAsId ? baseSku : itemDetails.product_id,
                'item_sku': baseSku,
                'item_category': cartItem.category,
                'price': priceUtils.formatPrice(itemDetails.base_price, priceFormat, false),
                'quantity': parseInt(itemDetails?.qty),
                'variation_id': cartItem.child_product_id ? (useSkuAsId ? cartItem.child_product_sku : cartItem.child_product_id) : undefined,
                'item_variant': cartItem.child_product_sku ? cartItem.child_product_sku : itemVariant
            }
        });
    }

    function getCartState() {
        const cartData = customerData.get('cart')();
        const priceFormat = Object.assign({...quote.getPriceFormat()}, {'pattern': '%s'});
        const useSkuAsId = window?.dataLayerConfig?.useSkuAsItemId || false;
        return {
            cart_id: cartData?.stape_cart_id,
            cart_quantity: quote?.totals()?.items_qty,
            currency: window.checkoutConfig?.quoteData?.quote_currency_code,
            cart_value: priceUtils.formatPrice(quote?.totals().grand_total, priceFormat, false),
            lines: quote?.getItems()?.map(item => {
                const cartItem = _.find(cartData.items, function(cartItem) {
                    return cartItem.item_id === item.item_id;
                });
                const baseSku = cartItem.product_sku;
                const itemSku = item.sku;
                const itemVariant = (itemSku !== baseSku && itemSku.indexOf(baseSku) === 0)
                    ? itemSku.substring(baseSku.length).replace(/^[-\s]+/, '')
                    : null;
                return {
                    'item_variant': cartItem.child_product_sku ? cartItem.child_product_sku : itemVariant,
                    'item_id': useSkuAsId ? baseSku : cartItem.product_id,
                    'item_name': item.name,
                    'item_sku': baseSku,
                    'quantity': item.qty,
                    'line_total_price': priceUtils.formatPrice(item?.row_total_incl_tax, priceFormat, false),
                    'price': priceUtils.formatPrice(item.price, priceFormat, false),
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
                        cart_total: quote.totals().grand_total,
                        cart_quantity: quote.totals().items_qty,
                        value: quote.totals()?.grand_total?.toString(),
                        items: prepareItems()
                    }
                })
                return response;
            });
        });
    }
});
