define([
    'jquery',
    'underscore',
    'ko',
    'Magento_Customer/js/customer-data'
], function($, _, ko, customerData) {
    'use strict';
    window.dataLayerConfig = {
        userDataEnabled: false
    };

    const customer = customerData.get('customer');

    function isLoggedIn() {
        return customer() && customer().firstname && customer().firstname.length > 0;
    }

    /**
     * Find product infor from cart
     *
     * @param productInfo
     * @returns {*|null}
     */
    function findItem(productInfo) {

        if (!productInfo) {
            return null;
        }

        const cartData = customerData.get('cart')();
        const hasOptions = productInfo?.optionValues?.length > 0;

        return _.find(cartData.items, function(item) {
            if (item.product_type === 'configurable' && hasOptions) {
                const values = item.options.map((option) => {
                    return option.option_value;
                });

                return item.product_id === productInfo.id
                    && JSON.stringify(values.sort()) === JSON.stringify(productInfo.optionValues.sort());
            }

            return item.product_id == productInfo.id || productInfo.id == item?.child_product_id;
        });
    }

    return function(config) {
        let wasAddToCartCalled = false;
        const cartData = customerData.get('cart');
        const lastAddedProduct = ko.observable(null);
        window.dataLayerConfig.userDataEnabled = config.isUserDataEnabled || false;
        window.dataLayer = window.dataLayer || [];

        if (config.isUserDataEnabled && isLoggedIn()) {
            config.data.user_data = Object.assign(config.data.user_data || {}, {
                email: customer().email,
                first_name: customer().firstname,
                last_name: customer().lastname,
                customer_id: customer().id
            });
        }

        dataLayer.push({ecommerce: null});
        if (config.data && config.data.event) {
            dataLayer.push(config.data);
        }
        cartData.subscribe(function(data) {
            const itemDetails = findItem(lastAddedProduct());
            try {
                if (wasAddToCartCalled && itemDetails) {
                    dataLayer.push({ecommerce: null});
                    window.dataLayer.push({
                        event: 'add_to_cart_stape',
                        ecomm_pagetype: 'product',
                        ecommerce: {
                            currency: config?.data?.ecommerce?.currency,
                            items: [
                                {
                                    'item_name': itemDetails.product_name,
                                    'item_id': itemDetails.product_id,
                                    'item_sku': itemDetails.product_sku,
                                    'item_category': itemDetails.category,
                                    'price': itemDetails.product_price_value,
                                    'quantity': itemDetails.qty,
                                    'variation_id': itemDetails.child_product_id ? itemDetails.child_product_id : undefined
                                }
                            ]
                        }
                    });
                }

                if (data?.stape_gtm_events?.remove_from_cart_stape) {
                    dataLayer.push({ecommerce: null});
                    window.dataLayer.push({
                        event: 'remove_from_cart_stape',
                        ecomm_pagetype: 'basket',
                        ecommerce: {
                            currency: config?.data?.ecommerce?.currency,
                            items: data?.stape_gtm_events?.remove_from_cart_stape?.items,
                        }
                    })
                }
            } catch(err) {
                console.error(err);
            }

            wasAddToCartCalled = false;
            lastAddedProduct(null);
        });

        $(document).on('ajax:addToCart', function(e, data) {
            const hasProductInfo = data?.productInfo?.length > 0;
            const hasProductIds = data?.productIds?.length > 0;

            if (!hasProductInfo && !hasProductIds) {
                console.error('Could not trigger `add_to_cart_stape` datalayer event. Product info is missing.');
                return;
            }

            let product = null;

            if (!hasProductInfo && hasProductIds) {
                product = {id: data?.productIds[data?.productIds?.length - 1]};
            }

            if (hasProductInfo) {
                product = data?.productInfo[data?.productInfo?.length - 1];
            }

            wasAddToCartCalled = true;
            lastAddedProduct(product);
        });

        $(document).on('ajax:removeFromCart', function(e, data) {
            const hasProductInfo = data.productInfo?.length > 0;
            const hasProductIds = data?.productIds?.length > 0;

            if (!hasProductInfo && !hasProductIds) {
                console.error('Could not trigger `remove_from_cart_stape` datalayer event. Product info is missing.');
                return;
            }

            let product = null;

            if (!hasProductInfo && hasProductIds) {
                product = {id: data?.productIds[0]};
            }

            if (hasProductInfo) {
                product = data?.productInfo[0];
            }

            const itemDetails = findItem(product);
            if (itemDetails) {
                dataLayer.push({ecommerce: null});
                window.dataLayer.push({
                    event: 'remove_from_cart_stape',
                    ecomm_pagetype: 'product',
                    ecommerce: {
                        currency: config?.data?.ecommerce?.currency,
                        items: [
                            {
                                'item_name': itemDetails.product_name,
                                'item_id': itemDetails.product_id,
                                'item_sku': itemDetails.product_sku,
                                'item_category': itemDetails.category,
                                'price': itemDetails.product_price_value,
                                'quantity': itemDetails.qty,
                                'variation_id': itemDetails.child_product_id ? itemDetails.child_product_id : undefined,
                            }
                        ]
                    }
                });
            }
        });
    }
});
