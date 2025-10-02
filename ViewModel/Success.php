<?php

namespace Stape\Gtm\ViewModel;

use Magento\Checkout\Model\Session;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Stape\Gtm\Model\Data\Order;
use Stape\Gtm\Model\Product\CategoryResolver;
use Stape\Gtm\Model\Datalayer\Formatter\Event as EventFormatter;

class Success implements ArgumentInterface
{
    /**
     * @var Json $json
     */
    private $json;

    /**
     * @var StoreManagerInterface $storeManager
     */
    private $storeManager;

    /**
     * @var Session $checkoutSession
     */
    private $checkoutSession;

    /**
     * @var PriceCurrencyInterface $priceCurrency
     */
    private $priceCurrency;

    /**
     * @var CategoryResolver $categoryResolver
     */
    private $categoryResolver;

    /**
     * @var Order $orderData
     */
    private $orderData;

    /**
     * @var EventFormatter $eventFormatter
     */
    private $eventFormatter;

    /**
     * Define class dependencies
     *
     * @param Json $json
     * @param StoreManagerInterface $storeManager
     * @param Session $checkoutSession
     * @param PriceCurrencyInterface $priceCurrency
     * @param CategoryResolver $categoryResolver
     * @param Order $orderData
     * @param EventFormatter $eventFormatter
     */
    public function __construct(
        Json $json,
        StoreManagerInterface $storeManager,
        Session $checkoutSession,
        PriceCurrencyInterface $priceCurrency,
        CategoryResolver $categoryResolver,
        Order $orderData,
        EventFormatter $eventFormatter
    ) {
        $this->json = $json;
        $this->storeManager = $storeManager;
        $this->checkoutSession = $checkoutSession;
        $this->priceCurrency = $priceCurrency;
        $this->categoryResolver = $categoryResolver;
        $this->orderData = $orderData;
        $this->eventFormatter = $eventFormatter;
    }

    /**
     * Retrieve order
     *
     * @return \Magento\Sales\Model\Order
     */
    private function getOrder()
    {
        return $this->checkoutSession->getLastRealOrder();
    }

    /**
     * Prepare items
     *
     * @param \Magento\Sales\Model\Order $order
     * @return array
     */
    public function prepareItems(\Magento\Sales\Model\Order $order)
    {
        $items = [];
        /** @var \Magento\Sales\Model\Order\Item $item */
        foreach ($order->getAllVisibleItems() as $item) {
            $category = $this->categoryResolver->resolve($item->getProduct());

            $itemCandidate = [
                'item_id' => $item->getProductId(),
                'item_name' => $item->getName(),
                'item_category' => $category ? $category->getName() : null,
                'price' => $this->priceCurrency->round($item->getBasePriceInclTax()),
                'quantity' => (int) $item->getQtyOrdered(),
                'item_sku' => $item->getSku(),
            ];

            if ($item->getHasChildren()) {
                $itemCandidate['variation_id'] = current($item->getChildrenItems())->getProductId();
                $itemCandidate['item_variant'] = current($item->getChildrenItems())->getSku();
            }

            $items[] = $itemCandidate;
        }
        return $items;
    }

    /**
     * Retrieve json
     *
     * @return bool|string
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getJson()
    {
        if (!$order = $this->getOrder()) {
            return null;
        }

        /** @var \Magento\Sales\Model\Order\Address $address */
        $address = $order->getBillingAddress();
        if (!$order->getIsVirtual()) {
            $address = $order->getShippingAddress();
        }

        return $this->json->serialize([
            'event' => $this->eventFormatter->formatName('purchase'),
            'ecomm_pagetype' => 'purchase',
            'user_data' => [
                'first_name' => $address->getFirstname(),
                'last_name' => $address->getLastname(),
                'email' => $address->getEmail(),
                'phone' => $address->getTelephone(),
                'customer_id' => $address->getCustomerId(),
                'country' => $address->getCountryId(),
                'region' => $address->getRegionCode(),
                'street' => implode(', ', $address->getStreet()),
                'city' => $address->getCity(),
                'zip' => $address->getPostcode(),
                'new_customer' => $this->orderData->isNewCustomer($address->getEmail()),
                'customer_lifetime_spent' => $this->priceCurrency->round(
                    $this->orderData->getLifetimeSpent($address->getEmail())
                ),
            ],
            'ecommerce' => [
                'currency' => $this->storeManager->getStore()->getCurrentCurrency()->getCode(),
                'transaction_id' => $order->getIncrementId(),
                'quote_id' => $order->getQuoteId(),
                'affiliation' => $this->storeManager->getStore()->getName(),
                'value' => $this->priceCurrency->round($order->getBaseGrandTotal()),
                'tax' => $this->priceCurrency->round($order->getBaseTaxAmount()), // tax
                'shipping' => $this->priceCurrency->round($order->getBaseShippingAmount()), // shipping price
                'coupon' => $order->getCouponCode(), // coupon if exists
                'sub_total' => $this->priceCurrency->round($order->getBaseSubtotal()),
                'discount_amount' => $this->priceCurrency->round($order->getBaseDiscountAmount()), //
                'items' => $this->prepareItems($order),
            ],
        ]);
    }
}
