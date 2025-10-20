<?php

namespace Stape\Gtm\ViewModel;

use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Stape\Gtm\Model\Datalayer\Formatter\Event as EventFormatter;

class Search implements ArgumentInterface, DatalayerInterface
{
    /**
     * @var StoreManagerInterface $storeManager
     */
    private $storeManager;

    /**
     * @var EventFormatter $eventFormatter
     */
    private $eventFormatter;

    /**
     * @var Category $categoryView
     */
    private $categoryView;

    /**
     * @var Json $json
     */
    private $json;

    /**
     * Define class dependencies
     *
     * @param Category $categoryView
     * @param EventFormatter $eventFormatter
     * @param StoreManagerInterface $storeManager
     * @param Json $json
     */
    public function __construct(
        Category              $categoryView,
        EventFormatter        $eventFormatter,
        StoreManagerInterface $storeManager,
        Json                  $json
    ) {
        $this->storeManager = $storeManager;
        $this->eventFormatter = $eventFormatter;
        $this->categoryView = $categoryView;
        $this->json = $json;
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
            'event' => $this->eventFormatter->formatName('view_collection'),
            'ecomm_pagetype' => 'search',
            'ecommerce' => [
                'currency' => $this->storeManager->getStore()->getCurrentCurrency()->getCode(),
                'item_list_name' => 'Search',
                'items' => $this->categoryView->prepareItems()
            ],
        ]);
    }
}
