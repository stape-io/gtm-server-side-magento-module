<?php

namespace Stape\Gtm\ViewModel;

use Magento\Catalog\Model\Layer;
use Magento\Catalog\Model\Layer\Resolver;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Framework\View\Layout;
use Magento\Store\Model\StoreManagerInterface;

class Category implements ArgumentInterface, DatalayerInterface
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
     * @var Layer $layer
     */
    private $layer;

    /** @var PriceCurrencyInterface $priceCurrency */
    private $priceCurrency;

    /** @var Layout $layout */
    private $layout;

    /**
     * Define class dependencies
     *
     * @param Json $json
     * @param StoreManagerInterface $storeManager
     * @param Resolver $layerResolver
     * @param PriceCurrencyInterface $priceCurrency
     * @param Layout $layout
     */
    public function __construct(
        Json $json,
        StoreManagerInterface $storeManager,
        Resolver $layerResolver,
        PriceCurrencyInterface $priceCurrency,
        Layout $layout
    ) {
        $this->json = $json;
        $this->storeManager = $storeManager;
        $this->layer = $layerResolver->get();
        $this->priceCurrency = $priceCurrency;
        $this->layout = $layout;
    }

    /**
     * Retrieve category name
     *
     * @return string
     */
    private function getCategoryName()
    {
        if ($currentCategory = $this->layer->getCurrentCategory()) {
            return $currentCategory->getName();
        }

        return '';
    }

    /**
     * Preparing items
     *
     * @return array
     */
    public function prepareItems()
    {
        /** @var \Magento\Catalog\Block\Product\ListProduct $productList */
        $productList = $this->layout->createBlock(\Magento\Catalog\Block\Product\ListProduct::class);
        $productList->getToolbarBlock()->setCollection($this->layer->getProductCollection());
        $collection = $productList->getLoadedProductCollection();
        $items = [];
        $index = 0;
        /** @var \Magento\Catalog\Model\Product $product */
        foreach ($collection as $product) {
            $items[] = [
                'item_name' => $product->getName(),
                'item_id' => $product->getId(),
                'item_sku' => $product->getSku(),
                'item_price' => $this->priceCurrency->round($product->getFinalPrice()),
                'index' => $index++
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
        return $this->json->serialize([
            'event' => 'view_collection_stape',
            'ecomm_pagetype' => 'category',
            'ecommerce' => [
                'currency' => $this->storeManager->getStore()->getCurrentCurrency()->getCode(),
                'item_list_name' => $this->getCategoryName(),
                'items' => $this->prepareItems()
            ],
        ]);
    }
}
