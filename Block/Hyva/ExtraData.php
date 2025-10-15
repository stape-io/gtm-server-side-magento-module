<?php

namespace Stape\Gtm\Block\Hyva;

use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Template\Context;
use Stape\Gtm\Model\Product\Mapper\EventItemsMapper;

class ExtraData extends \Magento\Framework\View\Element\Template
{

    /**
     * @var Registry $coreRegistry
     */
    private $coreRegistry;

    /**
     * @var Json $json
     */
    private $json;

    /**
     * @var EventItemsMapper $mapper
     */
    private $mapper;

    /**
     * Define class dependencies
     *
     * @param Context $context
     * @param Registry $coreRegistry
     * @param Json $json
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $coreRegistry,
        Json $json,
        EventItemsMapper $mapper,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->coreRegistry = $coreRegistry;
        $this->json = $json;
        $this->mapper = $mapper;
    }

    /**
     * Retrive current product
     *
     * @return \Magento\Catalog\Model\Product
     */
    public function getProduct()
    {
        return $this->coreRegistry->registry('current_product');
    }

    /**
     * Retrieve currency code
     *
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getCurrencyCode()
    {
        return $this->_storeManager->getStore()->getCurrentCurrency()->getCode();
    }

    /**
     * Convert array to json
     *
     * @param array $data
     * @return bool|string
     */
    public function dataToJson(array $data)
    {
        return $this->json->serialize($data);
    }

    /**
     * Convert products to event items
     *
     * @param array $items
     * @return array
     */
    public function toEventItems($items)
    {
        return $this->mapper->toEventItems($items);
    }
}
