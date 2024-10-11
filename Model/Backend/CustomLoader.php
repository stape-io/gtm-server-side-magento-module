<?php

namespace Stape\Gtm\Model\Backend;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Stape\Gtm\Model\ConfigProvider;
use Stape\Gtm\Model\Data\RandomString;
use Stape\Gtm\Model\Data\RandomSuffix;

class CustomLoader extends \Magento\Framework\App\Config\Value
{
    /**
     * @var RandomString $randomString
     */
    private $randomString;

    /**
     * @var RandomSuffix $randomSuffix
     */
    private $randomSuffix;

    /**
     * @var \Magento\Config\Model\ConfigFactory $configFactory
     */
    private $configFactory;

    /**
     * @var Json $jsonSerializer
     */
    private $jsonSerializer;

    /**
     * Define class dependencies
     *
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param ScopeConfigInterface $config
     * @param \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList
     * @param RandomString $randomString
     * @param RandomSuffix $randomSuffix
     * @param \Magento\Framework\Serialize\Serializer\Json $jsonSerializer
     * @param \Magento\Config\Model\ConfigFactory $configFactory
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        ScopeConfigInterface $config,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        \Stape\Gtm\Model\Data\RandomString $randomString,
        \Stape\Gtm\Model\Data\RandomSuffix $randomSuffix,
        \Magento\Config\Model\ConfigFactory $configFactory,
        \Magento\Framework\Serialize\Serializer\Json $jsonSerializer,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
        $this->randomString = $randomString;
        $this->randomSuffix = $randomSuffix;
        $this->configFactory = $configFactory;
        $this->jsonSerializer = $jsonSerializer;
    }

    /**
     * Save loader prefix
     *
     * @return void
     * @throws \Exception
     */
    protected function saveLoaderPrefix()
    {
        $prefix = $this->randomString->generate(5);
        $config = $this->configFactory->create();
        $config->setScope($this->getScope());
        $config->setScopeId($this->getScopeId());
        $config->setSection('stape_gtm');
        $config->setDataByPath(ConfigProvider::XPATH_GTM_LOADER_PREFIX, $prefix);
        $config->save();
    }

    /**
     * Save container id params
     *
     * @return void
     * @throws \Exception
     */
    protected function saveContainerIdParams()
    {
        parse_str($this->randomSuffix->generate($this->getValue()), $suffix);
        $value = $this->jsonSerializer->serialize([
            'prefix' => $this->randomString->generate(),
            'suffix' => $suffix,
        ]);

        $config = $this->configFactory->create();
        $config->setScope($this->getScope());
        $config->setScopeId($this->getScopeId());
        $config->setSection('stape_gtm');
        $config->setDataByPath(ConfigProvider::XPATH_GTM_CONTAINER_ID_PARAMS, $value);
        $config->save();
    }

    /**
     * Reset options that depend on custom loader
     *
     * @return void
     * @throws \Exception
     */
    protected function resetLinkedOptions()
    {
        $resetParams = [
            ConfigProvider::XPATH_GTM_STAPE_ANALYTICS_ENABLED,
            ConfigProvider::XPATH_GTM_KEEP_COOKIE,
        ];

        foreach ($resetParams as $path) {
            $config = $this->configFactory->create();
            $config->setScope($this->getScope());
            $config->setScopeId($this->getScopeId());
            $config->setSection('stape_gtm');
            $config->setDataByPath($path, false);
            $config->save();
        }
    }

    /**
     * Actions after saving
     *
     * @return CustomLoader
     */
    public function afterSave()
    {
        $loader = $this->getValue();

        try {

            $prefix = $this->_config->getValue(ConfigProvider::XPATH_GTM_LOADER_PREFIX, $this->getScope(), $this->getScopeId());

            if (!empty($loader) && empty($prefix)) {
                $this->saveLoaderPrefix();
            }

            $params = $this->_config->getValue(
                ConfigProvider::XPATH_GTM_CONTAINER_ID_PARAMS,
                $this->getScope(),
                $this->getScopeId()
            );

            if (!empty($loader) && empty($params)) {
                $this->saveContainerIdParams();
            }

            if (strlen($this->getValue()) < 1) {
                $this->resetLinkedOptions();
            }

        } catch (\Throwable $e) {

        }

        return parent::afterSave();
    }
}
