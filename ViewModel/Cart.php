<?php

namespace Stape\Gtm\ViewModel;

use Magento\Checkout\Model\Session;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Stape\Gtm\Model\ConfigProvider;
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
     * @var ConfigProvider $configProvider
     */
    private $configProvider;

    /**
     * Define class dependencies
     *
     * @param Json $json
     * @param StoreManagerInterface $storeManager
     * @param Session $checkoutSession
     * @param PriceCurrencyInterface $priceCurrency
     * @param CategoryResolver $categoryResolver
     * @param EventFormatter $eventFormatter
     * @param ConfigProvider $configProvider
     * @param ?PoolInterface $modifierPool
     */
    public function __construct(
        Json $json,
        StoreManagerInterface $storeManager,
        Session $checkoutSession,
        PriceCurrencyInterface $priceCurrency,
        CategoryResolver $categoryResolver,
        EventFormatter $eventFormatter,
        ConfigProvider $configProvider,
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
        $this->configProvider = $configProvider;
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
            $product = $item->getProduct();
            $category = $this->categoryResolver->resolve($product);

            $itemSku = $item->getSku();
            $baseSku = $product->getData('sku');
            $itemVariant = ($itemSku !== $baseSku && strpos($itemSku, $baseSku) === 0)
                ? ltrim(substr($itemSku, strlen($baseSku)), '- ')
                : null;

            $useSkuAsId = $this->configProvider->useSkuAsItemId();
            $childItem = $item->getHasChildren() ? current($item->getChildren()) : null;

            $items[] = [
                'item_name' => $item->getName(),
                'item_id' => $useSkuAsId ? $baseSku : $item->getProductId(),
                'item_sku' => $baseSku,
                'item_category' => $category ? $category->getName() : null,
                'price' => $this->priceCurrency->round($item->getBasePrice()),
                'quantity' => (int) $item->getQty(),
                'variation_id' => $childItem ? ($useSkuAsId ? $childItem->getSku() : $childItem->getProductId()) : null,
                'item_variant' => $childItem ? $childItem->getSku() : $itemVariant
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
