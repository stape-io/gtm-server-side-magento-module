<?php

namespace Stape\Gtm\Plugin\CustomerData;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Checkout\CustomerData\ItemPoolInterface;
use Magento\Quote\Model\Quote\Item;
use Stape\Gtm\Model\ConfigProvider;
use Stape\Gtm\Model\Data\ItemVariantFactory;
use Stape\Gtm\Model\Product\CategoryResolver;

class ItemPoolPlugin
{
    /**
     * @var ConfigProvider $config
     */
    protected $config;

    /**
     * @var CategoryResolver $categoryResolver
     */
    protected $categoryResolver;

    /**
     * @var ItemVariantFactory $itemVariantFactory
     */
    protected $itemVariantFactory;

    /**
     * Define class dependencies
     *
     * @param ConfigProvider $config
     * @param CategoryResolver $categoryResolver
     * @param ItemVariantFactory $itemVariantFactory
     */
    public function __construct(
        ConfigProvider $config,
        CategoryResolver $categoryResolver,
        ItemVariantFactory $itemVariantFactory
    ) {
        $this->config = $config;
        $this->categoryResolver = $categoryResolver;
        $this->itemVariantFactory = $itemVariantFactory;
    }

    /**
     * Override getItemData
     *
     * @param ItemPoolInterface $subject
     * @param array $result
     * @param Item $item
     * @return array
     */
    public function afterGetItemData(ItemPoolInterface $subject, $result, Item $item)
    {
        if (!$this->config->isActive() || !$this->config->ecommerceEventsEnabled()) {
            return $result;
        }

        if ($category = $this->categoryResolver->resolve($item->getProduct())) {
            $result['category'] = $category->getName();
        }

        if ($item->getHasChildren()) {
            $itemVariant = $this->itemVariantFactory->createFromQuoteItem($item);

            $result['child_product_id'] = $itemVariant->getVariationId();
            $result['child_product_sku'] = $itemVariant->getSku();
        }

        $result['product_sku'] = $item->getProduct()->getData(ProductInterface::SKU);
        $result['item_sku'] = $item->getSku();
        $result['added'] = strtotime($item->getCreatedAt());

        return $result;
    }
}
