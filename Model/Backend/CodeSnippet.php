<?php

namespace Stape\Gtm\Model\Backend;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Stape\Gtm\Block\Gtm;

class CodeSnippet extends \Magento\Framework\App\Config\Value
{
    /**
     * @var Gtm $block
     */
    private $block;

    /**
     * Define class dependencies
     *
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param Gtm $gtm
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        Gtm $gtm,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
        $this->block = $gtm;
    }

    /**
     * Retrieve block html
     *
     * @return string
     */
    public function getValue()
    {
        return $this->block->setNameInLayout('stape_gtm')->toHtml();
    }
}
