<?php

namespace Stape\Gtm\ViewModel\Wishlist;

use Magento\Catalog\Block\Product\Context;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Framework\View\Layout;
use Magento\Store\Model\StoreManagerInterface;
use Stape\Gtm\Model\Product\Mapper\EventItemsMapper;
use Stape\Gtm\ViewModel\DatalayerInterface;

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
     * @var \Magento\Wishlist\Helper\Data $wishlistHelper
     */
    protected $wishlistHelper;

    /**
     * Define class dependencies
     *
     * @param Layout $layout
     * @param StoreManagerInterface $storeManager
     * @param EventItemsMapper $mapper
     * @param Json $json
     * @param Context $context
     */
    public function __construct(
        Layout $layout,
        StoreManagerInterface $storeManager,
        EventItemsMapper $mapper,
        Json $json,
        Context $context
    ) {
        $this->layout = $layout;
        $this->storeManager = $storeManager;
        $this->mapper = $mapper;
        $this->json = $json;
        $this->wishlistHelper = $context->getWishlistHelper();
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
        $wishlist = $this->wishlistHelper->getWishlist();
        $items = array_map(function ($item) {
            return $item->getProduct();
        }, $wishlist->getItemCollection()->getItems());
        return [
            'currency' => $this->storeManager->getStore()->getCurrentCurrency()->getCode(),
            'lists' => [
                [
                    'item_list_name' => 'products',
                    'items' => $this->mapper->toEventItems($items)
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
