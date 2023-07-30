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
                'item_id': itemDetails.product_id,
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
                let address = quote.billingAddress();
                if (!quote.isVirtual()) {
                    address = Object.assign(address, quote.shippingAddress());
                }

                window.dataLayer.push({
                    event: 'payment_info_stape',
                    user_data: {
                        first_name: address.firstname,
                        last_name: address.lastname,
                        email: address.email || quote.customer_email,
                        phone: address.telephone,
                        country: address.countryId,
                        region: address.region,
                        city: address.city,
                        street: address.street.join(', '),
                        zip: address.postcode,
                        customer_id: quote.customer_id,
                    },
                    ecommerce: {
                        currency: window.checkoutConfig?.quoteData?.quote_currency_code,
                        cart_total: quote.totals().grand_total,
                        cart_quantity: quote.totals().items_qty,
                        items: prepareItems()
                    }
                })
                return response;
            });
        });
    }
});
