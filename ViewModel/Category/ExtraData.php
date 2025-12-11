<?php

namespace Stape\Gtm\ViewModel\Category;

use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Stape\Gtm\Model\Product\Mapper\EventItemsMapper;
use Stape\Gtm\ViewModel\Category;
use Stape\Gtm\ViewModel\DatalayerInterface;

class ExtraData implements ArgumentInterface, DatalayerInterface
{
    /**
     * @var Category $categoryViewModel
     */
    protected $categoryViewModel;

    /**
     * @var Json $json
     */
    protected $json;

    /**
     * @var EventItemsMapper $mapper
     */
    protected $mapper;

    /**
     * @var StoreManagerInterface $storeManager
     */
    protected $storeManager;

    /**
     * Define class dependencies
     *
     * @param Category $categoryViewModel
     * @param EventItemsMapper $mapper
     * @param StoreManagerInterface $storeManager
     * @param Json $json
     */
    public function __construct(
        Category                $categoryViewModel,
        EventItemsMapper        $mapper,
        StoreManagerInterface   $storeManager,
        Json                    $json
    ) {
        $this->categoryViewModel = $categoryViewModel;
        $this->mapper = $mapper;
        $this->storeManager = $storeManager;
        $this->json = $json;
    }

    /**
     * Retrieve list name
     *
     * @return string
     */
    protected function getListName()
    {
        return 'products';
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
        $collection = $this->categoryViewModel->getProductCollection();
        return [
            'currency' => $this->storeManager->getStore()->getCurrentCurrency()->getCode(),
            'lists' => [
                [
                    'item_list_name' => $this->getListName(),
                    'items' => $this->mapper->toEventItems($collection)
                ]
            ]
        ];
    }

    /**
     * Retrieve JSON
     *
     * @return string
     */
    public function getJson()
    {
        return $this->json->serialize($this->getEventData());
    }
}
