<?php

namespace Stape\Gtm\ViewModel;

use Magento\Checkout\Model\Session;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Stape\Gtm\Model\Product\CategoryResolver;

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
     * Define class dependencies
     *
     * @param Json $json
     * @param StoreManagerInterface $storeManager
     * @param Session $checkoutSession
     * @param PriceCurrencyInterface $priceCurrency
     * @param CategoryResolver $categoryResolver
     */
    public function __construct(
        Json $json,
        StoreManagerInterface $storeManager,
        Session $checkoutSession,
        PriceCurrencyInterface $priceCurrency,
        CategoryResolver $categoryResolver
    ) {
        $this->json = $json;
        $this->storeManager = $storeManager;
        $this->checkoutSession = $checkoutSession;
        $this->priceCurrency = $priceCurrency;
        $this->categoryResolver = $categoryResolver;
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
    private function prepareItems(\Magento\Sales\Model\Order $order)
    {
        $items = [];
        /** @var \Magento\Sales\Model\Order\Item $item */
        foreach ($order->getAllVisibleItems() as $item) {
            $category = $this->categoryResolver->resolve($item->getProduct());

            $items[] = [
                'item_id' => $item->getProductId(),
                'item_name' => $item->getName(),
                'item_category' => $category ? $category->getName() : null,
                'price' => $this->priceCurrency->round($item->getBasePrice()),
                'quantity' => (int) $item->getQtyOrdered(),
                'item_sku' => $item->getSku(),
            ];
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
            'event' => 'purchase_stape',
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
                'customer_lifetime_spent' => '',
                'new_customer' => $order->getCustomerIsGuest()
            ],
            'ecommerce' => [
                'currency' => $this->storeManager->getStore()->getCurrentCurrency()->getCode(),
                'transaction_id' => $order->getIncrementId(),
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
