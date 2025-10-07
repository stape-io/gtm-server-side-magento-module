<?php

namespace Stape\Gtm\Model\Product\Mapper;

use Magento\Catalog\Block\Product\Context;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Store\Model\StoreManagerInterface;
use Stape\Gtm\Model\Product\CategoryResolver;

class EventItemsMapper
{

    /**
     * @var PriceCurrencyInterface $priceCurrency
     */
    protected $priceCurrency;

    /**
     * @var StoreManagerInterface $storeManager
     */
    protected $storeManager;

    /**
     * @var CategoryResolver $categoryResolver
     */
    protected $categoryResolver;

    /**
     * @var Context $context
     */
    protected $context;

    /**
     * Define class dependencies
     *
     * @param PriceCurrencyInterface $priceCurrency
     * @param StoreManagerInterface $storeManager
     * @param CategoryResolver $categoryResolver
     * @param Context $context
     */
    public function __construct(
        PriceCurrencyInterface $priceCurrency,
        StoreManagerInterface $storeManager,
        CategoryResolver $categoryResolver,
        Context $context
    ) {
        $this->priceCurrency = $priceCurrency;
        $this->storeManager = $storeManager;
        $this->categoryResolver = $categoryResolver;
        $this->context = $context;
    }

    /**
     * Map product collection to event items collection
     *
     * @param \Magento\Catalog\Model\Product[]|\Magento\Catalog\Model\ResourceModel\Product\Collection $itemList
     * @return array
     */
    public function toEventItems($itemList)
    {
        $items = [];
        $index = 0;
        $imageBuilder = $this->context->getImageBuilder();

        /** @var \Magento\Catalog\Model\Product $product */
        foreach ($itemList as $product) {
            $category = $this->categoryResolver->resolve($product);
            $items[$product->getId()] = [
                'imageUrl' => $imageBuilder->create($product, 'category_page_grid')->getImageUrl(),
                'item_name' => $product->getName(),
                'item_id' => $product->getId(),
                'item_sku' => $product->getSku(),
                'price' => $this->priceCurrency->round($product->getFinalPrice()),
                'index' => $index++,
                'quantity' => '1',
                'variant_name' => $product->getName(),
                'item_category' => $category ? $category->getName() : null,
            ];
        }
        return $items;
    }
}
