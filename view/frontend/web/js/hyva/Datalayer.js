let stapeCustomerData = {};
const eventRegistry = {};

export class Datalayer {
    constructor(config) {
        this.config = Object.assign({
            eventSuffix: ''
        }, config || {},);
        this.initEventHandlers();
    }

    /**
     * Init js event handlers
     */
    initEventHandlers() {
        const _this = this;
        window.addEventListener('private-content-loaded', e => { _this.initCustomerData(e) });
        window.addEventListener('private-content-loaded', e => { _this.privateContentLoadHandler(e) });
        window.addEventListener("DOMContentLoaded", e => { _this.initProductItemEventHandlers(e) });
    }

    /**
     * Initialize customer data
     * @param event
     */
    initCustomerData(event) {
        const customer = event?.detail?.data?.customer;

        if (customer.customerLoggedIn && typeof eventData.user_data === 'undefined') {
            stapeCustomerData = Object.keys(gtmData).filter((key) => {
                return key.indexOf('customer') !== 0;
            }).reduce((cur, key) => { return Object.assign(cur, { [key]: gtmData[key] })}, {});
        }
    }

    /**
     * Populate/Override extra data
     *
     * @param extraData
     */
    addExtraData(extraData) {
        this.config.extraData = Object.assign(extraData, this.config.extraData);
    }

    /**
     *
     * @returns {{}}
     */
    getCustomerData() {
        return stapeCustomerData;
    }

    /**
     * Init product event handlers
     */
    initProductItemEventHandlers() {
        const productElements = document.querySelectorAll(
            this.config?.productItemSelector + ' a'
        );

        productElements.forEach(productEl => {
            productEl.addEventListener('click', e => this.selectItemEventHandler(e))
        });
    }

    /**
     * Push data layer event
     * @param eventData
     */
    pushEventData(eventData) {
        window.dataLayer = window.dataLayer || [];

        const isEventAllowed = eventRegistry[eventData.event] !== undefined
            ? !eventRegistry[eventData.event] : true;

        if (isEventAllowed && eventData.ecommerce) {
            window.dataLayer.push({ecommerce: null});
        }

        if (isEventAllowed) {
            window.dataLayer.push(eventData);
        }

        eventRegistry[eventData.event] = true;
    }

    /**
     * Product listing click handler
     *
     * @param e
     */
    selectItemEventHandler(e) {
        try {
            let productId = e.currentTarget
                .closest(this.config.productItemSelector + ' form')
                ?.querySelector('input[name="product"]')
                ?.value;

            if (!productId) {
                productId = e.currentTarget
                    .closest(this.config.productItemSelector)
                    ?.querySelector('[data-product-id]')
                    ?.attributes['data-product-id']
                    ?.value;

            }

            const allowedTypes = this.config?.extraData?.lists.map(list => list.item_list_name);
            const productLists = this.config.extraData.lists || [];
            const type = allowedTypes.find(
                sectionType => productLists.find(
                    (list, idx) => list['item_list_name'] === sectionType && list.items[productId] !== undefined
                )
            );

            const items = this.config?.extraData?.lists.find(list => list.item_list_name === type).items || [];
            const productInfo = items[productId];
            const eventName = `select_item${this.config.eventSuffix}`;

            // de-register event name for multiple selections
            eventRegistry[eventName] = undefined;

            this.pushEventData({
                event: eventName,
                ecomm_pagetype: this.config?.pageType,
                ecommerce: {
                    currency: this.config?.extraData?.currency,
                    value: productInfo.price,
                    item_list_name: type,
                    items: [
                        productInfo
                    ]
                }
            });

        } catch (err) {
            console.log(`Stape. Could not build event data: ${err.message}`)
        }
    }

    /**
     * Private content event handler
     * @param event
     */
    privateContentLoadHandler(event) {
        let eventData = this.config.defaultEventData;
        const _this = this;
        if (typeof eventData.customer === 'undefined' && stapeCustomerData && !eventData.user_data) {
            eventData.user_data = stapeCustomerData;
        }

        _this.pushEventData(eventData);

        const cartEvents = event?.detail?.data?.cart?.stape_gtm_events;
        if (cartEvents) {
            Object.keys(cartEvents).map(function(eventCode) {
                const cartEventData = {
                    event: eventCode,
                    ecommerce: cartEvents[eventCode]
                };

                if (eventData.user_data) {
                    cartEventData.user_data = eventData.user_data;
                }

                eventRegistry[eventData.event] = undefined;
                _this.pushEventData(cartEventData);
            });
        }
        event.detail.data.cart.stape_gtm_events = undefined;
    }
}
