<?php

namespace Stape\Gtm\ViewModel\Wishlist;

use Magento\Catalog\Block\Product\Context;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Framework\View\Layout;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Wishlist\Block\Customer\Wishlist;
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
     * Retrieve json
     *
     * @return bool|string
     */
    public function getJson()
    {
        $wishlist = $this->wishlistHelper->getWishlist();
        $items = array_map(function($item) {
            return $item->getProduct();
        }, $wishlist->getItemCollection()->getItems());

        return $this->json->serialize([
            'currency' => $this->storeManager->getStore()->getCurrentCurrency()->getCode(),
            'lists' => [
                [
                    'item_list_name' => 'products',
                    'items' => $this->mapper->toEventItems($items)
                ]
            ]
        ]);
    }
}
