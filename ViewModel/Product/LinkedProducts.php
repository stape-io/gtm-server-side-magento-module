<?php

namespace Stape\Gtm\ViewModel\Product;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\View\Layout;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Stape\Gtm\Model\Product\Mapper\EventItemsMapper;
use Stape\Gtm\ViewModel\DatalayerInterface;



class LinkedProducts implements ArgumentInterface, DatalayerInterface
{

    /**
     * @var string[]
     */
    private $blockTypes = [
        'catalog.product.related' => 'related',
        'product.info.upsell' => 'upsell',
        'checkout.cart.crosssell' => 'crosssell',
    ];

    /**
     * @var Layout $layout
     */
    private $layout;

    /**
     * @var EventItemsMapper $mapper
     */
    private $mapper;

    /**
     * @var StoreManagerInterface $storeManager
     */
    private $storeManager;

    /**
     * @var Json $json
     */
    private $json;

    /**
     * @var EventManagerInterface $eventManager
     */
    private $eventManager;

    /**
     * @var DataObjectFactory $dataObjectFactory
     */
    private $dataObjectFactory;

    /**
     * Define class dependencies
     *
     * @param Layout $layout
     * @param EventItemsMapper $mapper
     * @param StoreManagerInterface $storeManager
     * @param EventManagerInterface $eventManager
     * @param Json $json
     * @param DataObjectFactory $dataObjectFactory
     * @return void
     */
    public function __construct(
        Layout                  $layout,
        EventItemsMapper        $mapper,
        StoreManagerInterface   $storeManager,
        EventManagerInterface   $eventManager,
        Json                    $json,
        DataObjectFactory       $dataObjectFactory
    ) {
        $this->layout = $layout;
        $this->mapper = $mapper;
        $this->storeManager = $storeManager;
        $this->eventManager = $eventManager;
        $this->json = $json;
        $this->dataObjectFactory = $dataObjectFactory;
    }

    /**
     * Build JSON
     *
     * @return bool|string
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getJson()
    {
        $result = [
            'currency' => $this->storeManager->getStore()->getCurrentCurrency()->getCode(),
        ];

        $transport = $this->dataObjectFactory->create(['data' => ['block_types' => $this->blockTypes]]);
        $this->eventManager->dispatch('stape_gtm_linked_product_block_map', ['transport' => $transport]);

        if (is_array($transport->getBlockTypes())) {
            foreach ($transport->getBlockTypes() as $blockName => $blockType) {
                if (!$block = $this->layout->getBlock($blockName)) {
                    continue;
                }

                if ($block && method_exists($block, 'getItems')) {
                    $result['lists'][] = [
                        'item_list_name' => $blockType,
                        'items' => $this->mapper->toEventItems($block->getItems()),
                    ];
                }
            }
        }

        return $this->json->serialize($result);
    }
}
