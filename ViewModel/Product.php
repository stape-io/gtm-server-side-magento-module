<?php

namespace Stape\Gtm\ViewModel;

use Magento\Catalog\Helper\Data;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Stape\Gtm\Model\Datalayer\Modifier\PoolInterface;
use Stape\Gtm\Model\Price\CatalogPrice;
use Stape\Gtm\Model\Price\CurrencyResolver;
use Stape\Gtm\Model\Product\CategoryResolver;
use Stape\Gtm\Model\Datalayer\Formatter\Event as EventFormatter;

class Product extends DatalayerAbstract implements ArgumentInterface
{
    /**
     * @var Registry $registry
     */
    private $registry;

    /**
     * @var Data $catalogHelper
     */
    private $catalogHelper;

    /**
     * @var CategoryResolver $categoryResolver
     */
    private $categoryResolver;

    /**
     * @var CatalogPrice $catalogPrice
     */
    private $catalogPrice;

    /**
     * Define class dependencies
     *
     * @param Json $json
     * @param StoreManagerInterface $storeManager
     * @param Registry $registry
     * @param Data $catalogHelper
     * @param CategoryResolver $categoryResolver
     * @param PriceCurrencyInterface $priceCurrency
     * @param EventFormatter $eventFormatter
     * @param CurrencyResolver $currencyResolver
     * @param CatalogPrice $catalogPrice
     */
    public function __construct(
        Json $json,
        StoreManagerInterface $storeManager,
        Registry $registry,
        Data $catalogHelper,
        CategoryResolver $categoryResolver,
        PriceCurrencyInterface $priceCurrency,
        EventFormatter $eventFormatter,
        CurrencyResolver $currencyResolver,
        CatalogPrice $catalogPrice
    ) {
        parent::__construct($json, $eventFormatter, $storeManager, $priceCurrency, $currencyResolver);
        $this->registry = $registry;
        $this->catalogHelper = $catalogHelper;
        $this->categoryResolver = $categoryResolver;
        $this->catalogPrice = $catalogPrice;
    }

    /**
     * Retrieve current product
     *
     * @return \Magento\Catalog\Model\Product|null
     */
    private function getProduct()
    {
        if ($product = $this->registry->registry('product')) {
            return $product;
        }

        return null;
    }

    /**
     * Retrieve category name
     *
     * @param \Magento\Catalog\Model\Product $product
     * @return mixed|string|null
     */
    private function getCategoryName($product)
    {
        $path = $this->catalogHelper->getBreadcrumbPath();
        if ($product && count($path) == 1) {
            $category = $this->getLastCategory($product);
            return  $category ? $category->getName() : null;
        } elseif (count($path) > 1) {
            end($path);
            return prev($path)['label'] ?? null;
        }

        return '';
    }

    /**
     * Retrieve last category from product
     *
     * @param \Magento\Catalog\Model\Product $product
     * @return \Magento\Catalog\Model\Category
     */
    public function getLastCategory($product)
    {
        return $this->categoryResolver->resolve($product);
    }

    /**
     * Preparing product information
     *
     * @return array
     */
    public function getProductData()
    {
        if (!$product = $this->getProduct()) {
            return [];
        }

        return [
            'item_name' => $product->getName(),
            'item_id' => $product->getId(),
            'item_sku' => $product->getSku(),
            'item_category' => $this->getCategoryName($product),
            'price' => $this->formatPrice($this->catalogPrice->forProduct($product)),
        ];
    }

    /**
     * Retrieve event data
     *
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getEventData()
    {
        $value = $this->formatPrice($this->catalogPrice->forProduct($this->getProduct()));
        return [
            'event' => $this->eventFormatter->formatName('view_item'),
            'ecomm_pagetype' => 'product',
            'ecommerce' => [
                'value' => $value,
                'currency' => $this->currencyResolver->codeForStore(),
                'items' => array_filter([
                    $this->getProductData()
                ])
            ],
        ];
    }
}
