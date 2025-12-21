<?php

namespace Stape\Gtm\Model\Datalayer\Modifier;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Checkout\Model\Session;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Stape\Gtm\Model\Data\ItemVariantFactory;

class CartState implements ModifierInterface
{

    /**
     * @var Session $checkoutSession
     */
    protected $checkoutSession;

    /**
     * @var PriceCurrencyInterface $priceCurrency
     */
    protected $priceCurrency;

    /**
     * @var ItemVariantFactory $itemVariantFactory
     */
    protected $itemVariantFactory;

    /**
     * Define class dependencies
     *
     * @param Session $checkoutSession
     * @param PriceCurrencyInterface $priceCurrency
     * @param ItemVariantFactory $itemVariantFactory
     */
    public function __construct(
        Session $checkoutSession,
        PriceCurrencyInterface $priceCurrency,
        ItemVariantFactory $itemVariantFactory
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->priceCurrency = $priceCurrency;
        $this->itemVariantFactory = $itemVariantFactory;
    }

    /**
     * Retrieve quote
     *
     * @return \Magento\Quote\Api\Data\CartInterface|\Magento\Quote\Model\Quote
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function getQuote()
    {
        try {
            return $this->checkoutSession->getQuote();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Preparing cart items
     *
     * @param \Magento\Quote\Model\Quote $quote
     * @return array
     */
    protected function prepareItems(\Magento\Quote\Model\Quote $quote)
    {
        $items = [];
        foreach ($quote->getAllVisibleItems() as $item) {
            $itemVariant = $this->itemVariantFactory->createFromQuoteItem($item);
            $items[] = [
                'item_variant' => $itemVariant->getSku(),
                'item_id' => $item->getId(),
                'item_sku' => $item->getProduct()->getData(ProductInterface::SKU),
                'item_name' => $item->getName(),
                'quantity' => $item->getQty(),
                'line_total_price' => $this->priceCurrency->round($item->getRowTotalInclTax()),
                'price' => $this->priceCurrency->round($item->getPrice()),
            ];
        }
        return $items;
    }

    /**
     * Preparing cart data
     *
     * @param \Magento\Quote\Model\Quote $quote
     * @return array
     */
    protected function getCartData($quote)
    {
        return [
            'cart_id' => $this->checkoutSession->getData('stape_cart_id'),
            'cart_quantity' => (int) $quote->getItemsQty(),
            'cart_value' => $this->priceCurrency->round($quote->getBaseGrandTotal()),
            'currency' => $quote->getBaseCurrencyCode(),
            'lines' => $this->prepareItems($quote)
        ];
    }

    /**
     * Modify event data
     *
     * @param array $data
     * @return array|array[]
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function modifyEventData($data)
    {
        if (!$quote = $this->getQuote()) {
            return $data;
        }
        $data['ecommerce']['cart_state'] = $this->getCartData($quote);
        return $data;
    }
}
