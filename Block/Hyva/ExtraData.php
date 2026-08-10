<?php

namespace Stape\Gtm\Block\Hyva;

use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Template\Context;
use Stape\Gtm\Model\Product\Mapper\EventItemsMapper;
use Stape\Gtm\Model\Price\CurrencyResolver;

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
     * @var CurrencyResolver $currencyResolver
     */
    private $currencyResolver;

    /**
     * Define class dependencies
     *
     * @param Context $context
     * @param Registry $coreRegistry
     * @param Json $json
     * @param EventItemsMapper $mapper
     * @param CurrencyResolver $currencyResolver
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $coreRegistry,
        Json $json,
        EventItemsMapper $mapper,
        CurrencyResolver $currencyResolver,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->coreRegistry = $coreRegistry;
        $this->json = $json;
        $this->mapper = $mapper;
        $this->currencyResolver = $currencyResolver;
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
        return $this->currencyResolver->codeForStore();
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
