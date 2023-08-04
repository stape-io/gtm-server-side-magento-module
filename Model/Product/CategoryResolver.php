<?php

namespace Stape\Gtm\Model\Product;

class CategoryResolver
{
    /**
     * Resolve category
     *
     * @param \Magento\Catalog\Model\Product $product
     * @return false|mixed
     */
    public function resolve($product)
    {
        $categoryCollection = clone $product->getCategoryCollection();
        $categoryCollection->clear();
        $categoryCollection->addAttributeToSort('level', $categoryCollection::SORT_ORDER_DESC)
            ->addAttributeToFilter('path', ['like' => "1/" . $product->getStore()->getRootCategoryId() . "/%"]);
        $categories = $categoryCollection->setPageSize(1)->getFirstItem()->getParentCategories();
        return end($categories);
    }
}
