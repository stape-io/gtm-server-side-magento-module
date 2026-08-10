<?php

namespace Stape\Gtm\ViewModel\Compare;

use Magento\Catalog\Block\Product\Compare\ListCompare;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Framework\View\Layout;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Wishlist\Block\Customer\Wishlist;
use Stape\Gtm\Model\Product\Mapper\EventItemsMapper;
use Stape\Gtm\ViewModel\DatalayerInterface;
use Stape\Gtm\Model\Price\CurrencyResolver;

class ExtraData implements ArgumentInterface, DatalayerInterface
{

    /**
     * @var Layout $layout
     */
    protected $layout;

    /**
     * @var StoreManagerInterface $storeManager
     */
    protected $storeManager;

    /**
     * @var EventItemsMapper $mapper
     */
    protected $mapper;

    /**
     * @var Json $json
     */
    protected $json;

    /**
     * @var CurrencyResolver $currencyResolver
     */
    private $currencyResolver;

    /**
     * Define class dependencies
     *
     * @param Layout $layout
     * @param StoreManagerInterface $storeManager
     * @param EventItemsMapper $mapper
     * @param Json $json
     * @param CurrencyResolver $currencyResolver
     */
    public function __construct(
        Layout $layout,
        StoreManagerInterface $storeManager,
        EventItemsMapper $mapper,
        Json $json,
        CurrencyResolver $currencyResolver
    ) {
        $this->layout = $layout;
        $this->storeManager = $storeManager;
        $this->mapper = $mapper;
        $this->json = $json;
        $this->currencyResolver = $currencyResolver;
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
        $compareList = $this->layout->createBlock(ListCompare::class);
        return [
            'currency' => $this->currencyResolver->codeForStore(),
            'lists' => [
                [
                    'item_list_name' => 'products',
                    'items' => $this->mapper->toEventItems($compareList->getItems())
                ]
            ]
        ];
    }

    /**
     * Retrieve json
     *
     * @return bool|string
     */
    public function getJson()
    {
        return $this->json->serialize($this->getEventData());
    }
}
