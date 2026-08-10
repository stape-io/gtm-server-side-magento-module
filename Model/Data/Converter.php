<?php

namespace Stape\Gtm\Model\Data;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Stape\Gtm\Model\Price\FormatsPrice;
use Stape\Gtm\Model\Price\CurrencyResolver;
use Stape\Gtm\Model\Price\ItemPrice;
use Stape\Gtm\Model\Price\Totals;
use Stape\Gtm\Model\Product\CategoryResolver;

class Converter
{
    use FormatsPrice;

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
     * @var ItemVariantFactory
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
     * @var CurrencyResolver $currencyResolver
     */
    private $currencyResolver;

    /**
     * Define class dependencies
     *
     * @param CategoryResolver $categoryResolver
     * @param PriceCurrencyInterface $priceCurrency
     * @param \Stape\Gtm\Model\Data\Order $orderData
     * @param ItemVariantFactory $itemVariantFactory
     * @param ItemPrice $itemPrice
     * @param Totals $totals
     * @param CurrencyResolver $currencyResolver
     */
    public function __construct(
        CategoryResolver $categoryResolver,
        PriceCurrencyInterface $priceCurrency,
        \Stape\Gtm\Model\Data\Order $orderData,
        ItemVariantFactory $itemVariantFactory,
        ItemPrice $itemPrice,
        Totals $totals,
        CurrencyResolver $currencyResolver
    ) {
        $this->categoryResolver = $categoryResolver;
        $this->priceCurrency = $priceCurrency;
        $this->orderData = $orderData;
        $this->itemVariantFactory = $itemVariantFactory;
        $this->itemPrice = $itemPrice;
        $this->totals = $totals;
        $this->currencyResolver = $currencyResolver;
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
            $itemVariant = $this->itemVariantFactory->createFromOrderItem($item);
            $items[] = [
                'item_id' => $item->getProductId(),
                'item_name' => $item->getName(),
                'item_sku' => $item->getProduct()->getData(ProductInterface::SKU),
                'item_category' => $category ? $category->getName() : '',
                'price' => $this->formatPrice($this->itemPrice->forSalesItem($item, $item->getStoreId())),
                'quantity' => $item->getQtyOrdered(),
                'item_variant' => $itemVariant->getSku(),
                'variation_id' => $itemVariant->getVariationId(),
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
            $itemVariant = $this->itemVariantFactory->createFromOrderItem($item->getOrderItem());

            $items[] = [
                'item_id' => $item->getProductId(),
                'item_name' => $item->getName(),
                'item_sku' => $orderItem->getProduct()->getData(ProductInterface::SKU),
                'item_category' => $category ? $category->getName() : '',
                'price' => $this->formatPrice($this->itemPrice->forSalesItem($item, $orderItem->getStoreId())),
                'quantity' => $item->getQty(),
                'item_variant' => $itemVariant->getSku(),
                'variation_id' => $itemVariant->getVariationId(),
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
        $storeId = $order->getStoreId();

        return [
            'transaction_id' => $order->getIncrementId(),
            'quote_id' => $order->getQuoteId(),
            'affiliation' => $order->getStoreName(),
            'value' => $this->formatPrice($this->totals->forEntity($order, Totals::FIELD_GRAND_TOTAL, $storeId)),
            'tax' => $this->formatPrice($this->totals->forEntity($order, Totals::FIELD_TAX_AMOUNT, $storeId)),
            'shipping' => $this->formatPrice(
                $this->totals->forEntity($order, Totals::FIELD_SHIPPING_AMOUNT, $storeId)
            ),
            'coupon' => $order->getCouponCode(),
            'discount_amount' => $this->formatPrice(
                $this->totals->forEntity($order, Totals::FIELD_DISCOUNT_AMOUNT, $storeId)
            ),
            'currency' => $this->currencyResolver->codeForOrder($order, $storeId),
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
        $order = $creditMemo->getOrder();
        $storeId = $order->getStoreId();

        return [
            'transaction_id' => $order->getIncrementId(),
            'affiliation' => $order->getStoreName(),
            'value' => $this->formatPrice(
                $this->totals->forEntity($creditMemo, Totals::FIELD_GRAND_TOTAL, $storeId)
            ),
            'tax' => $this->formatPrice($this->totals->forEntity($creditMemo, Totals::FIELD_TAX_AMOUNT, $storeId)),
            'shipping' => $this->formatPrice(
                $this->totals->forEntity($creditMemo, Totals::FIELD_SHIPPING_AMOUNT, $storeId)
            ),
            'coupon' => $order->getCouponCode(),
            'discount_amount' => $this->formatPrice(
                $this->totals->forEntity($creditMemo, Totals::FIELD_DISCOUNT_AMOUNT, $storeId)
            ),
            'currency' => $this->currencyResolver->codeForOrder($order, $storeId),
            'items' => $this->prepareCreditMemoItems($creditMemo)
        ];
    }
}
