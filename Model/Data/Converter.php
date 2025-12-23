<?php

namespace Stape\Gtm\Model\Data;

use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Stape\Gtm\Model\Product\CategoryResolver;

class Converter
{
    /**
     * @var CategoryResolver $categoryResolver
     */
    private $categoryResolver;

    /**
     * @var PriceCurrencyInterface $priceCurrency
     */
    private $priceCurrency;

    /**
     * @var \Stape\Gtm\Model\Data\Order $orderData
     */
    private $orderData;

    /**
     * Define class dependencies
     *
     * @param CategoryResolver $categoryResolver
     * @param PriceCurrencyInterface $priceCurrency
     * @param \Stape\Gtm\Model\Data\Order $orderData
     */
    public function __construct(
        CategoryResolver $categoryResolver,
        PriceCurrencyInterface $priceCurrency,
        \Stape\Gtm\Model\Data\Order $orderData
    ) {
        $this->categoryResolver = $categoryResolver;
        $this->priceCurrency = $priceCurrency;
        $this->orderData = $orderData;
    }

    /**
     * Prepare order items
     *
     * @param Order $order
     * @return array
     */
    public function prepareOrderItems(Order $order)
    {
        $items = [];
        /** @var \Magento\Sales\Model\Order\Item $item */
        foreach ($order->getAllVisibleItems() as $item) {
            $product = $item->getProduct();
            $category = $product ? $this->categoryResolver->resolve($product) : null;
            $childItem = $item->getHasChildren() ? current($item->getChildrenItems() ?? []) : null;
            $items[] = [
                'item_id' => $item->getProductId(),
                'item_name' => $item->getName(),
                'item_sku' => $item->getSku(),
                'item_category' => $category ? $category->getName() : '',
                'price' => $this->priceCurrency->round($item->getBasePrice()),
                'quantity' => $item->getQtyOrdered(),
                'item_variant' => $childItem ? $childItem->getSku() : null,
                'variation_id' => $childItem ? $childItem->getProductId() : null,
                'purchase_type' => false,
            ];
        }
        return $items;
    }
    /**
     * Prepare order items
     *
     * @param Creditmemo $creditmemo
     * @return array
     */
    public function prepareCreditMemoItems(Creditmemo $creditmemo)
    {
        $items = [];
        /** @var \Magento\Sales\Model\Order\Creditmemo\Item $item */
        foreach ($creditmemo->getItems() as $item) {
            $orderItem = $item->getOrderItem();
            if ($orderItem->getParentItemId()) {
                continue;
            }

            $category = $this->categoryResolver->resolve($orderItem->getProduct());
            $childItem = $item->getOrderItem()->getHasChildren()
                ? current($item->getOrderItem()->getChildrenItems() ?? []) : null;
            $items[] = [
                'item_id' => $item->getProductId(),
                'item_name' => $item->getName(),
                'item_sku' => $item->getSku(),
                'item_category' => $category ? $category->getName() : '',
                'price' => $this->priceCurrency->round($item->getBasePrice()),
                'quantity' => $item->getQty(),
                'item_variant' => $childItem ? $childItem->getSku() : null,
                'variation_id' => $childItem ? $childItem->getProductId() : null,
            ];
        }
        return $items;
    }

    /**
     * Extract user data from order
     *
     * @param Order $order
     * @return array
     */
    public function orderToUserData(Order $order)
    {
        /** @var \Magento\Sales\Model\Order\Address $address */
        $address = $order->getIsVirtual() ? $order->getBillingAddress() : $order->getShippingAddress();

        return [
            'email' => $order->getCustomerEmail(),
            'first_name' => $order->getCustomerFirstname(),
            'last_name' => $order->getCustomerLastname(),
            'phone' => $address->getTelephone(),
            'country' => $address->getCountryId(),
            'region' => $address->getRegion(),
            'street' => implode(', ', $address->getStreet()),
            'city' => $address->getCity(),
            'zip' => $address->getPostcode(),
            'customer_id' => $order->getCustomerId(),
            'new_customer' => $this->orderData->isNewCustomer($order->getCustomerEmail()),
        ];
    }

    /**
     * Extrace e-com data from order
     *
     * @param Order $order
     * @return array
     */
    public function orderToEcomData(Order $order)
    {
        return [
            'transaction_id' => $order->getIncrementId(),
            'quote_id' => $order->getQuoteId(),
            'affiliation' => $order->getStoreName(),
            'value' => $this->priceCurrency->round($order->getBaseGrandTotal()),
            'tax' => $this->priceCurrency->round($order->getBaseTaxAmount()),
            'shipping' => $this->priceCurrency->round($order->getBaseShippingAmount()),
            'coupon' => $order->getCouponCode(),
            'discount_amount' => $this->priceCurrency->round($order->getBaseDiscountAmount()),
            'currency' => $order->getOrderCurrencyCode(),
            'items' => $this->prepareOrderItems($order)
        ];
    }

    /**
     * Convert credit memo into ecom data
     *
     * @param Creditmemo $creditMemo
     * @return array
     */
    public function creditMemoToEcom(Creditmemo $creditMemo)
    {
        return [
            'transaction_id' => $creditMemo->getOrder()->getIncrementId(),
            'affiliation' => $creditMemo->getOrder()->getStoreName(),
            'value' => $this->priceCurrency->round($creditMemo->getBaseGrandTotal()),
            'tax' => $this->priceCurrency->round($creditMemo->getBaseTaxAmount()),
            'shipping' => $this->priceCurrency->round($creditMemo->getBaseShippingAmount()),
            'coupon' => $creditMemo->getOrder()->getCouponCode(),
            'discount_amount' => $this->priceCurrency->round($creditMemo->getBaseDiscountAmount()),
            'currency' => $creditMemo->getOrder()->getOrderCurrencyCode(),
            'items' => $this->prepareCreditMemoItems($creditMemo)
        ];
    }
}
