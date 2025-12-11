<?php

namespace Stape\Gtm\ViewModel;

use Magento\Checkout\Model\Session;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Stape\Gtm\Model\Datalayer\Modifier\PoolInterface;
use Stape\Gtm\Model\Product\CategoryResolver;
use Stape\Gtm\Model\Datalayer\Formatter\Event as EventFormatter;

class Cart extends DatalayerAbstract implements ArgumentInterface
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
     * Define class dependencies
     *
     * @param Json $json
     * @param StoreManagerInterface $storeManager
     * @param Session $checkoutSession
     * @param PriceCurrencyInterface $priceCurrency
     * @param CategoryResolver $categoryResolver
     * @param EventFormatter $eventFormatter
     * @param ?PoolInterface $modifierPool
     */
    public function __construct(
        Json $json,
        StoreManagerInterface $storeManager,
        Session $checkoutSession,
        PriceCurrencyInterface $priceCurrency,
        CategoryResolver $categoryResolver,
        EventFormatter $eventFormatter,
        ?PoolInterface $modifierPool = null
    ) {
        parent::__construct(
            $json,
            $eventFormatter,
            $storeManager,
            $priceCurrency,
            $modifierPool
        );
        $this->checkoutSession = $checkoutSession;
        $this->categoryResolver = $categoryResolver;
    }

    /**
     * Prepare items
     *
     * @param \Magento\Quote\Model\Quote $quote
     * @return array
     */
    public function prepareItems(\Magento\Quote\Model\Quote $quote)
    {
        $items = [];
        /** @var \Magento\Quote\Model\Quote\Item $item */
        foreach ($quote->getAllVisibleItems() as $item) {
            $category = $this->categoryResolver->resolve($item->getProduct());
            $items[] = [
                'item_name' => $item->getName(),
                'item_id' => $item->getProductId(),
                'item_sku' => $item->getSku(),
                'item_category' => $category ? $category->getName() : null,
                'price' => $this->priceCurrency->round($item->getBasePrice()),
                'quantity' => (int) $item->getQty(),
                'variation_id' => $item->getHasChildren() ? current($item->getChildren())->getProductId() : null,
                'item_variant' => $item->getHasChildren() ? current($item->getChildren())->getSku() : null
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
        /** @var \Magento\Quote\Model\Quote $quote */
        if (!$quote = $this->checkoutSession->getQuote()) {
            return null;
        }

        return [
            'event' => $this->eventFormatter->formatName('view_cart'),
            'ecomm_pagetype' => 'basket',
            'cart_quantity' => (int) $quote->getItemsQty(),
            'cart_total' => $this->priceCurrency->round($quote->getBaseGrandTotal()),
            'ecommerce' => [
                'value' => (string) $this->priceCurrency->round($quote->getBaseGrandTotal()),
                'currency' => $this->storeManager->getStore()->getCurrentCurrency()->getCode(),
                'items' => $this->prepareItems($quote),
            ],
        ];
    }
}
