<?php

namespace Stape\Gtm\Plugin\CustomerData;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Checkout\CustomerData\ItemPoolInterface;
use Magento\Quote\Model\Quote\Item;
use Stape\Gtm\Model\ConfigProvider;
use Stape\Gtm\Model\Data\ItemVariantFactory;
use Stape\Gtm\Model\Price\ItemPrice;
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
     * @var ItemPrice $itemPrice
     */
    protected $itemPrice;

    /**
     * Define class dependencies
     *
     * @param ConfigProvider $config
     * @param CategoryResolver $categoryResolver
     * @param ItemVariantFactory $itemVariantFactory
     * @param ItemPrice $itemPrice
     */
    public function __construct(
        ConfigProvider $config,
        CategoryResolver $categoryResolver,
        ItemVariantFactory $itemVariantFactory,
        ItemPrice $itemPrice
    ) {
        $this->config = $config;
        $this->categoryResolver = $categoryResolver;
        $this->itemVariantFactory = $itemVariantFactory;
        $this->itemPrice = $itemPrice;
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

        // Resolved server side so the tax and currency flags are honoured in one place.
        $result['stape_price'] = $this->itemPrice->forQuoteItem($item);
        $result['stape_line_total'] = $this->itemPrice->rowTotalForQuoteItem($item);

        $result['product_sku'] = $item->getProduct()->getData(ProductInterface::SKU);
        $result['item_sku'] = $item->getSku();
        $result['added'] = strtotime($item->getCreatedAt());

        return $result;
    }
}
