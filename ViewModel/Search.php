<?php

namespace Stape\Gtm\ViewModel;

use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Stape\Gtm\Model\Price\CurrencyResolver;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Stape\Gtm\Model\Datalayer\Formatter\Event as EventFormatter;
use Stape\Gtm\Model\Datalayer\Modifier\PoolInterface;

class Search extends DatalayerAbstract implements ArgumentInterface
{
    /**
     * @var Category $categoryView
     */
    private $categoryView;

    /**
     * Define class dependencies
     *
     * @param Json $json
     * @param EventFormatter $eventFormatter
     * @param StoreManagerInterface $storeManager
     * @param PriceCurrencyInterface $priceCurrency
     * @param Category $categoryView
     * @param CurrencyResolver $currencyResolver
     */
    public function __construct(
        Json                    $json,
        EventFormatter          $eventFormatter,
        StoreManagerInterface   $storeManager,
        PriceCurrencyInterface  $priceCurrency,
        Category                $categoryView,
        CurrencyResolver        $currencyResolver
    ) {
        parent::__construct($json, $eventFormatter, $storeManager, $priceCurrency, $currencyResolver);
        $this->categoryView = $categoryView;
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
        return [
            'event' => $this->eventFormatter->formatName('view_collection'),
            'ecomm_pagetype' => 'search',
            'ecommerce' => [
                'currency' => $this->currencyResolver->codeForStore(),
                'item_list_name' => 'Search',
                'items' => $this->categoryView->prepareItems()
            ],
        ];
    }
}
