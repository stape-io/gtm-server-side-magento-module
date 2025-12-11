<?php

namespace Stape\Gtm\ViewModel;

use Magento\Catalog\Model\Layer;
use Magento\Catalog\Model\Layer\Resolver;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Framework\View\Layout;
use Magento\Store\Model\StoreManagerInterface;
use Stape\Gtm\Model\ConfigProvider;
use Stape\Gtm\Model\Datalayer\Formatter\Event as EventFormatter;
use Stape\Gtm\Model\Datalayer\Modifier\PoolInterface;

class Category extends DatalayerAbstract implements ArgumentInterface
{
    /**
     * @var Layer $layer
     */
    private $layer;

    /** @var Layout $layout */
    private $layout;

    /**
     * @var ConfigProvider $configProvider
     */
    private $configProvider;

    /**
     * @var ManagerInterface $eventManager
     */
    private $eventManager;

    /**
     * Define class dependencies
     *
     * @param Json $json
     * @param StoreManagerInterface $storeManager
     * @param Resolver $layerResolver
     * @param PriceCurrencyInterface $priceCurrency
     * @param Layout $layout
     * @param ConfigProvider $configProvider
     * @param ManagerInterface $eventManager
     * @param EventFormatter $eventFormatter
     */
    public function __construct(
        Json $json,
        StoreManagerInterface $storeManager,
        Resolver $layerResolver,
        PriceCurrencyInterface $priceCurrency,
        Layout $layout,
        ConfigProvider $configProvider,
        ManagerInterface $eventManager,
        EventFormatter $eventFormatter,
    ) {
        parent::__construct($json, $eventFormatter, $storeManager, $priceCurrency);
        $this->layer = $layerResolver->get();
        $this->layout = $layout;
        $this->configProvider = $configProvider;
        $this->eventManager = $eventManager;
    }

    /**
     * Retrieve collection
     *
     * @return \Magento\Catalog\Model\ResourceModel\Product\Collection
     */
    private function prepareCollection()
    {
        /** @var \Magento\Catalog\Block\Product\ListProduct $productList */
        $productList = $this->layout->createBlock(\Magento\Catalog\Block\Product\ListProduct::class);
        $productCollection = $this->layer->getProductCollection();

        // if collection is loaded there is some third-party/custom code loading it and conflicting the Stape module
        if ($productCollection->isLoaded()) {
            return $productCollection;
        }

        // cloning to avoid interfering with original product listing
        $collection = clone $productCollection;

        $toolbar = $productList->getToolbarBlock();
        $toolbar->setCollection($collection);
        $collection->setPageSize($this->configProvider->getCollectionSize());
        $collection->setCurPage(1);

        $this->eventManager->dispatch(
            'catalog_block_product_list_collection',
            ['collection' => $collection]
        );

        return $collection;
    }

    /**
     * Retrieve product collection
     *
     * @return \Magento\Catalog\Model\ResourceModel\Product\Collection
     */
    public function getProductCollection()
    {
        return $this->prepareCollection();
    }

    /**
     * Retrieve category name
     *
     * @return string
     */
    public function getCategoryName()
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
        $collection = $this->getProductCollection();
        $items = [];
        $index = 0;
        $useSkuAsId = $this->configProvider->useSkuAsItemId();
        /** @var \Magento\Catalog\Model\Product $product */
        foreach ($collection as $product) {
            $items[] = [
                'item_name' => $product->getName(),
                'item_id' => $useSkuAsId ? $product->getSku() : $product->getId(),
                'item_sku' => $product->getSku(),
                'item_price' => $this->priceCurrency->round($product->getFinalPrice()),
                'index' => $index++
            ];
        }
        return $items;
    }

    /**
     * Retrieve event data
     */
    public function getEventData()
    {
        return [
            'event' => $this->eventFormatter->formatName('view_collection'),
            'ecomm_pagetype' => 'category',
            'ecommerce' => [
                'currency' => $this->storeManager->getStore()->getCurrentCurrency()->getCode(),
                'item_list_name' => $this->getCategoryName(),
                'items' => $this->prepareItems()
            ],
        ];
    }
}
