<?php

namespace Stape\Gtm\Plugin\CustomerData;

use Magento\Checkout\CustomerData\ItemPoolInterface;
use Magento\Quote\Model\Quote\Item;
use Stape\Gtm\Model\ConfigProvider;
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
     * Define class dependencies
     *
     * @param ConfigProvider $config
     * @param CategoryResolver $categoryResolver
     */
    public function __construct(
        ConfigProvider $config,
        CategoryResolver $categoryResolver
    ) {
        $this->config = $config;
        $this->categoryResolver = $categoryResolver;
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

        return $result;
    }
}
