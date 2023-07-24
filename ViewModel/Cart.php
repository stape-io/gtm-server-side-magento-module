<?php

namespace Stape\Gtm\ViewModel;

use Magento\Checkout\Model\Session;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Stape\Gtm\Model\Product\CategoryResolver;

class Cart implements ArgumentInterface
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
     * Prepare items
     *
     * @param \Magento\Quote\Model\Quote $quote
     * @return array
     */
    private function prepareItems(\Magento\Quote\Model\Quote $quote)
    {
        $items = [];
        /** @var \Magento\Quote\Model\Quote\Item $item */
        foreach ($quote->getAllVisibleItems() as $item) {
            $category = $this->categoryResolver->resolve($item->getProduct());
            $items[] = [
                'item_name' => $item->getName(),
                'item_id' => $item->getId(),
                'item_sku' => $item->getSku(),
                'item_category' => $category ? $category->getName() : null,
                'price' => $item->getBasePrice(),
                'quantity' => $item->getQtyOrdered(),
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
        /** @var \Magento\Quote\Model\Quote $quote */
        if (!$quote = $this->checkoutSession->getQuote()) {
            return null;
        }

        return $this->json->serialize([
            'event' => 'view_cart_stape',
            'cart_quantity' => $quote->getItemsQty(),
            'cart_total' => $quote->getBaseGrandTotal(),
            'ecommerce' => [
                'currency' => $this->storeManager->getStore()->getCurrentCurrency()->getCode(),
                'items' => $this->prepareItems($quote),
            ],
        ]);
    }
}
