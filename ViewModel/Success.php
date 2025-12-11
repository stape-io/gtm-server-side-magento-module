<?php

namespace Stape\Gtm\ViewModel;

use Magento\Checkout\Model\Session;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Stape\Gtm\Model\ConfigProvider;
use Stape\Gtm\Model\Data\Order;
use Stape\Gtm\Model\Datalayer\Modifier\PoolInterface;
use Stape\Gtm\Model\Product\CategoryResolver;
use Stape\Gtm\Model\Datalayer\Formatter\Event as EventFormatter;

class Success extends DatalayerAbstract implements ArgumentInterface
{

    /**
     * @var Session $checkoutSession
     */
    private $checkoutSession;

    /**
     * @var CategoryResolver $categoryResolver
     */
    private $categoryResolver;

    /**
     * @var Order $orderData
     */
    private $orderData;

    /**
     * @var ConfigProvider $configProvider
     */
    private $configProvider;

    /**
     * Define class dependencies
     *
     * @param Json $json
     * @param EventFormatter $eventFormatter
     * @param StoreManagerInterface $storeManager
     * @param Session $checkoutSession
     * @param PriceCurrencyInterface $priceCurrency
     * @param CategoryResolver $categoryResolver
     * @param Order $orderData
     * @param ConfigProvider $configProvider
     */
    public function __construct(
        Json $json,
        EventFormatter $eventFormatter,
        StoreManagerInterface $storeManager,
        Session $checkoutSession,
        PriceCurrencyInterface $priceCurrency,
        CategoryResolver $categoryResolver,
        Order $orderData,
        ConfigProvider $configProvider
    ) {
        parent::__construct($json, $eventFormatter, $storeManager, $priceCurrency);
        $this->checkoutSession = $checkoutSession;
        $this->categoryResolver = $categoryResolver;
        $this->orderData = $orderData;
        $this->configProvider = $configProvider;
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
            $product = $item->getProduct();
            $category = $this->categoryResolver->resolve($product);

            $itemSku = $item->getSku();
            $baseSku = $product->getData('sku');
            $itemVariant = ($itemSku !== $baseSku && strpos($itemSku, $baseSku) === 0)
                ? ltrim(substr($itemSku, strlen($baseSku)), '- ')
                : null;

            $useSkuAsId = $this->configProvider->useSkuAsItemId();
            $childItem = $item->getHasChildren() ? current($item->getChildrenItems()) : null;

            $items[] = [
                'item_id' => $useSkuAsId ? $baseSku : $item->getProductId(),
                'item_name' => $item->getName(),
                'item_category' => $category ? $category->getName() : null,
                'price' => $this->priceCurrency->round($item->getBasePriceInclTax()),
                'quantity' => (int) $item->getQtyOrdered(),
                'item_sku' => $baseSku,
                'purchase_type' => false,
                'variation_id' => $childItem ? ($useSkuAsId ? $childItem->getSku() : $childItem->getProductId()) : null,
                'item_variant' => $childItem ? $childItem->getSku() : $itemVariant,
            ];
        }
        return $items;
    }

    /**
     * Retrieve event data
     *
     * @return array|null
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getEventData()
    {
        if (!$order = $this->getOrder()) {
            return null;
        }

        /** @var \Magento\Sales\Model\Order\Address $address */
        $address = $order->getBillingAddress();
        if (!$order->getIsVirtual()) {
            $address = $order->getShippingAddress();
        }

        return [
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
                'value' => (string) $this->priceCurrency->round($order->getBaseGrandTotal()),
                'tax' => $this->priceCurrency->round($order->getBaseTaxAmount()), // tax
                'shipping' => $this->priceCurrency->round($order->getBaseShippingAmount()), // shipping price
                'coupon' => $order->getCouponCode(), // coupon if exists
                'sub_total' => $this->priceCurrency->round($order->getBaseSubtotal()),
                'discount_amount' => $this->priceCurrency->round($order->getBaseDiscountAmount()), //
                'items' => $this->prepareItems($order),
            ],
        ];
    }
}
