define([
    'mage/utils/wrapper',
    'underscore',
    'Magento_Checkout/js/model/quote',
    'Magento_Customer/js/customer-data'
], function(wrapper, _, quote, customerData) {
    'use strict';

    /**
     * Prepare quote items for data layer
     *
     * @returns {*}
     */
    function prepareItems() {
        const cartData = customerData.get('cart')();
        return quote.getItems().map(function(itemDetails) {
            cartData.items.find
            const cartItem = _.find(cartData.items, function(cartItem) {
                return cartItem.item_id === itemDetails.item_id;
            });
            return {
                'item_name': itemDetails.name,
                'item_id': itemDetails.item_id,
                'item_sku': itemDetails.sku,
                'item_category': cartItem.category,
                'price': itemDetails.base_price,
                'quantity': itemDetails.qty,
            }
        });
    }

    /**
     * Customizing logic to push info into datalayer
     */
    return function(setPaymentInformation) {
        return wrapper.wrap(setPaymentInformation, function(originalAction, messageContainer, paymentData) {
            return originalAction(messageContainer, paymentData).then(function(response) {
                const address = quote.isVirtual() ? quote.billingAddress() : quote.shippingAddress();
                window.dataLayer = window.dataLayer || [];
                window.dataLayer.push({
                    event: 'payment_info_stape',
                    user_data: {
                        first_name: address.first_name,
                        last_name: address.last_name,
                        email: address.email,
                        phone: address.phone,
                        country: address.country,
                        region: address.region,
                        city: address.city,
                        street: address.street.join(', '),
                        zip: address.postcode,
                        customer_id: quote.customer_id,
                    },
                    ecommerce: {
                        currency: quote.currency_code,
                        cart_total: quote.grand_total,
                        cart_quantity: quote.qty,
                        items: prepareItems()
                    }
                })
                return response;
            });
        });
    }
});
