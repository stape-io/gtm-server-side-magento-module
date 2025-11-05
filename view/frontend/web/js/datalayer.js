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

            return item.product_id == productInfo.id;
        });
    }

    return function(config) {
        let wasAddToCartCalled = false;
        const productItemselector = config.productItemSelector || '.product-item';
        const cartData = customerData.get('cart');
        const lastAddedProduct = ko.observable(null);
        window.dataLayerConfig.userDataEnabled = config.isUserDataEnabled || false;
        window.dataLayerConfig.stapeEventSuffix = config?.suffix;
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
            const itemDetails = findItem(lastAddedProduct())
            if (wasAddToCartCalled) {
                dataLayer.push({ecommerce: null});
                window.dataLayer.push({
                    event: 'add_to_cart' + config?.suffix,
                    ecomm_pagetype: 'product',
                    ecommerce: {
                        value: itemDetails.product_price_value,
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
                                'item_variant': itemDetails.child_product_sku ? itemDetails.child_product_sku : undefined
                            }
                        ]
                    }
                });
            }

            if (data?.stape_gtm_events?.remove_from_cart_stape) {
                dataLayer.push({ecommerce: null});
                const eventName = 'remove_from_cart' +  config?.suffix;
                window.dataLayer.push({
                    event: eventName,
                    ecomm_pagetype: 'basket',
                    ecommerce: {
                        value: data?.stape_gtm_events[eventName].value,
                        currency: config?.data?.ecommerce?.currency,
                        items: data?.stape_gtm_events[eventName]?.items,
                    }
                })
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
                    event: 'remove_from_cart' + config?.suffix,
                    ecomm_pagetype: 'product',
                    ecommerce: {
                        value: itemDetails.product_price_value,
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
                                'item_variant': itemDetails.child_product_sku ? itemDetails.child_product_sku : undefined
                            }
                        ]
                    }
                });
            }
        });
        $(document).on('click', productItemselector + ' a', function(e, data) {

            if (config?.extraData === undefined) {
                console.log('Stape module. Extra data missing');
                return;
            }

            const productInfoWrapper = $(e.target.closest(productItemselector));
            if (productInfoWrapper.get(0) === undefined) {
                console.log('Stape module. Could not find product-item-info wrapper.');
                return;
            }

            const allowedTypes = config?.extraData?.lists.map(list => list.item_list_name);
            const sectionWrapper = $(e.target.closest('.products.wrapper'));
            const type = allowedTypes.find(sectionType => sectionWrapper.hasClass('products-' + sectionType)) || 'products';

            const productId = productInfoWrapper.find('[data-product-id]').data('product-id');
            const items = _.find(config.extraData.lists, list => list.item_list_name === type).items || [];
            const productInfo = items[productId];
            if (productInfo) {
                window.dataLayer.push({
                    event: 'select_item' + config?.suffix,
                    ecomm_pagetype: config?.pageType,
                    ecommerce: {
                        currency: config?.extraData?.currency,
                        value: productInfo.price,
                        item_list_name: type,
                        items: [
                            productInfo
                        ]
                    },
                    user_data: config?.data?.user_data || {}
                })
            }
        });
    }
});
