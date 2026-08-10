define([
    'jquery',
    'underscore',
    'ko',
    'Magento_Customer/js/customer-data'
], function($, _, ko, customerData) {
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
     * Unit price of a cart item, resolved server side so the tax and currency
     * settings are honoured. Falls back to the display-currency value Magento
     * ships while a previously cached cart section has not been refreshed yet.
     *
     * @param {Object} item
     * @returns {Number|undefined}
     */
    function itemPrice(item) {
        return item?.stape_price ?? item?.product_price_value;
    }

    /**
     * Line total of a cart item, see itemPrice for the fallback rationale.
     *
     * @param {Object} item
     * @returns {Number|undefined}
     */
    function itemLineTotal(item) {
        return item?.stape_line_total ?? (item?.product_price_value * item?.qty);
    }

    /**
     * Cart value and currency, resolved server side.
     *
     * @param {Object} data
     * @returns {Number|String|undefined}
     */
    function cartValue(data) {
        return data?.stape_cart_value ?? data?.subtotalAmount;
    }

    function cartCurrency(data, config) {
        return data?.stape_currency || config?.data?.ecommerce?.currency;
    }

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

    function getItemVariantSku(itemSku, baseSku) {
        return (itemSku !== baseSku && itemSku.indexOf(baseSku) === 0)
            ? itemSku.substring(baseSku.length).replace(/^[-\s]+/, '')
            : null;
    }

    function getLastItemFromCart() {
        const cartData = customerData.get('cart')();
        return _.first(_.sortBy(cartData.items, 'added').reverse());
    }

    return function(config) {
        let wasAddToCartCalled = false;
        const productItemselector = config.productItemSelector || '.product-item';
        const cartData = customerData.get('cart');
        const lastAddedProduct = ko.observable(null);
        window.dataLayerConfig.userDataEnabled = config.isUserDataEnabled || false;
        window.dataLayerConfig.stapeEventSuffix = config?.suffix;
        window.dataLayerConfig.useDisplayCurrency = config?.useDisplayCurrency || false;
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
            let itemDetails = findItem(lastAddedProduct());

            if (itemDetails === undefined) {
                itemDetails = getLastItemFromCart();
            }

            if (wasAddToCartCalled) {
                dataLayer.push({ecommerce: null});
                if (!itemDetails) {
                    return;
                }
                const baseSku = itemDetails?.product_sku;
                const itemSku = itemDetails.item_sku || itemDetails?.product_sku;
                const itemVariantSku = getItemVariantSku(itemSku, baseSku);
                window.dataLayer.push({
                    event: 'add_to_cart' + config?.suffix,
                    ecomm_pagetype: 'product',
                    ecommerce: {
                        cart_state: {
                            cart_id: data?.stape_cart_id,
                            cart_quantity: data.summary_count,
                            currency: cartCurrency(data, config),
                            cart_value: toMoney(cartValue(data)),
                            lines: data.items.map(item => {
                                const lineBaseSku = item.product_sku;
                                const lineItemSku = item.item_sku || item.product_sku;
                                const variantSku = getItemVariantSku(lineItemSku, lineBaseSku);
                                return {
                                    item_variant: item.child_product_sku ? item.child_product_sku : variantSku,
                                    item_id: item.product_id,
                                    item_name: item.product_name,
                                    item_sku: lineBaseSku,
                                    quantity: item.qty,
                                    line_total_price: toMoney(itemLineTotal(item)),
                                    price: toMoney(itemPrice(item)),
                                }}
                            )
                        },
                        value: toMoney(itemPrice(itemDetails) * itemDetails?.qty),
                        currency: cartCurrency(data, config),
                        items: [
                            {
                                'item_name': itemDetails.product_name,
                                'item_id': itemDetails.product_id,
                                'item_sku': baseSku,
                                'item_category': itemDetails.category,
                                'price': toMoney(itemPrice(itemDetails)),
                                'quantity': itemDetails.qty,
                                'variation_id': itemDetails.child_product_id ? itemDetails.child_product_id : undefined,
                                'item_variant': itemDetails.child_product_sku ? itemDetails.child_product_sku : itemVariantSku
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
                        cart_state: data?.stape_gtm_events[eventName]?.cart_state || undefined,
                        value: toMoney(data?.stape_gtm_events[eventName]?.value),
                        currency: data?.stape_gtm_events[eventName]?.currency
                            || cartCurrency(data, config),
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

                const baseSku = itemDetails?.product_sku;
                const itemSku = itemDetails.item_sku || itemDetails?.product_sku;
                const itemVariantSku = getItemVariantSku(itemSku, baseSku);

                dataLayer.push({ecommerce: null});
                window.dataLayer.push({
                    event: 'remove_from_cart' + config?.suffix,
                    ecomm_pagetype: 'product',
                    ecommerce: {
                        value: toMoney(itemPrice(itemDetails) * itemDetails?.qty),
                        currency: cartCurrency(cartData(), config),
                        items: [
                            {
                                'item_name': itemDetails.product_name,
                                'item_id': itemDetails.product_id,
                                'item_sku': baseSku,
                                'item_category': itemDetails.category,
                                'price': toMoney(itemPrice(itemDetails)),
                                'quantity': itemDetails.qty,
                                'variation_id': itemDetails.child_product_id ? itemDetails.child_product_id : undefined,
                                'item_variant': itemDetails.child_product_sku ? itemDetails.child_product_sku : itemVariantSku
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
                        value: toMoney(productInfo?.price),
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
