<?php

namespace Stape\Gtm\ViewModel;

use Magento\Catalog\Model\Layer;
use Magento\Catalog\Model\Layer\Resolver;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;

class Category implements ArgumentInterface
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

    /**
     * Define class dependencies
     *
     * @param Json $json
     * @param StoreManagerInterface $storeManager
     * @param Resolver $layerResolver
     */
    public function __construct(
        Json $json,
        StoreManagerInterface $storeManager,
        Resolver $layerResolver
    ) {
        $this->json = $json;
        $this->storeManager = $storeManager;
        $this->layer = $layerResolver->get();
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
    private function prepareItems()
    {
        $items = [];
        $index = 0;

        /** @var \Magento\Catalog\Model\Product $product */
        foreach ($this->layer->getProductCollection() as $product) {
            $items[] = [
                'item_name' => $product->getName(),
                'item_id' => $product->getId(),
                'item_sku' => $product->getSku(),
                'item_price' => $product->getFinalPrice(),
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
            'ecommerce' => [
                'currency' => $this->storeManager->getStore()->getCurrentCurrency()->getCode(),
                'item_list_name' => $this->getCategoryName(),
                'items' => $this->prepareItems()
            ],
        ]);
    }
}
