<?php

namespace Stape\Gtm\ViewModel;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Checkout\Model\Session;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Stape\Gtm\Model\Data\ItemVariantFactory;
use Stape\Gtm\Model\Data\Order;
use Stape\Gtm\Model\Product\CategoryResolver;
use Stape\Gtm\Model\Datalayer\Formatter\Event as EventFormatter;
use Stape\Gtm\Model\Price\CurrencyResolver;
use Stape\Gtm\Model\Price\ItemPrice;
use Stape\Gtm\Model\Price\Totals;

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
     * @var ItemVariantFactory $itemVariantFactory
     */
    private $itemVariantFactory;

    /**
     * @var ItemPrice $itemPrice
     */
    private $itemPrice;

    /**
     * @var Totals $totals
     */
    private $totals;

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
     * @param ItemVariantFactory $itemVariantFactory
     * @param ItemPrice $itemPrice
     * @param Totals $totals
     * @param CurrencyResolver $currencyResolver
     */
    public function __construct(
        Json $json,
        EventFormatter $eventFormatter,
        StoreManagerInterface $storeManager,
        Session $checkoutSession,
        PriceCurrencyInterface $priceCurrency,
        CategoryResolver $categoryResolver,
        Order $orderData,
        ItemVariantFactory $itemVariantFactory,
        ItemPrice $itemPrice,
        Totals $totals,
        CurrencyResolver $currencyResolver
    ) {
        parent::__construct($json, $eventFormatter, $storeManager, $priceCurrency, $currencyResolver);
        $this->checkoutSession = $checkoutSession;
        $this->categoryResolver = $categoryResolver;
        $this->orderData = $orderData;
        $this->itemVariantFactory = $itemVariantFactory;
        $this->itemPrice = $itemPrice;
        $this->totals = $totals;
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
                'price' => $this->formatPrice($this->itemPrice->forSalesItem($item, $order->getStoreId())),
                'quantity' => (int) $item->getQtyOrdered(),
                'item_sku' => $item->getProduct()->getData(ProductInterface::SKU),
                'purchase_type' => false,
            ];

            $itemVariant = $this->itemVariantFactory->createFromOrderItem($item);
            $itemCandidate['variation_id'] = $itemVariant->getVariationId();
            $itemCandidate['item_variant'] = $itemVariant->getSku();

            $items[] = $itemCandidate;
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

        $storeId = $order->getStoreId();

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
                'customer_lifetime_spent' => $this->formatPrice(
                    $this->currencyResolver->convertFromBaseCurrency(
                        $this->orderData->getLifetimeSpent($address->getEmail()),
                        $storeId
                    )
                ),
            ],
            'ecommerce' => [
                'currency' => $this->currencyResolver->codeForOrder($order, $storeId),
                'transaction_id' => $order->getIncrementId(),
                'quote_id' => $order->getQuoteId(),
                'affiliation' => $this->storeManager->getStore()->getName(),
                'value' => $this->formatPrice(
                    $this->totals->forEntity($order, Totals::FIELD_GRAND_TOTAL, $storeId)
                ),
                'tax' => $this->formatPrice(
                    $this->totals->forEntity($order, Totals::FIELD_TAX_AMOUNT, $storeId)
                ), // tax
                'shipping' => $this->formatPrice(
                    $this->totals->forEntity($order, Totals::FIELD_SHIPPING_AMOUNT, $storeId)
                ), // shipping price
                'coupon' => $order->getCouponCode(), // coupon if exists
                'sub_total' => $this->formatPrice(
                    $this->totals->forEntity($order, Totals::FIELD_SUBTOTAL, $storeId)
                ),
                'discount_amount' => $this->formatPrice(
                    $this->totals->forEntity($order, Totals::FIELD_DISCOUNT_AMOUNT, $storeId)
                ), //
                'items' => $this->prepareItems($order),
            ],
        ];
    }
}
