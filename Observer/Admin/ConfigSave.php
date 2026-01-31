<?php

namespace Stape\Gtm\Observer\Admin;

use Magento\Config\Model\ConfigFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ScopeResolverInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Stape\Gtm\Model\Api\Loader;
use Stape\Gtm\Model\ConfigProvider;

class ConfigSave implements ObserverInterface
{
    /**
     * @var Loader
     */
    private $loaderApi;

    /**
     * @var ConfigFactory $configFactory
     */
    private $configFactory;

    /**
     * @var StoreManagerInterface $storeManager
     */
    private $storeManager;

    /**
     * @var LoggerInterface $logger
     */
    private $logger;

    /**
     * Define class dependencies
     *
     * @param Loader $loaderApi
     * @param StoreManagerInterface $storeManager
     * @param ConfigFactory $configFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        Loader $loaderApi,
        StoreManagerInterface $storeManager,
        ConfigFactory $configFactory,
        LoggerInterface $logger
    ) {
        $this->loaderApi = $loaderApi;
        $this->storeManager = $storeManager;
        $this->configFactory = $configFactory;
        $this->logger = $logger;
    }

    /**
     * Resolve scope
     *
     * @param string $scopeType
     * @param int $scopeId
     * @return \Magento\Store\Api\Data\StoreInterface|\Magento\Store\Api\Data\WebsiteInterface|null
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function resolveScope($scopeType, $scopeId)
    {
        if ($scopeType === ScopeConfigInterface::SCOPE_TYPE_DEFAULT) {
            return null;
        }

        if ($scopeType === ScopeInterface::SCOPE_STORE) {
            return $this->storeManager->getStore($scopeId);
        }

        return $this->storeManager->getWebsite($scopeId);
    }

    /**
     * Execute config save
     *
     * @param Observer $observer
     * @return void
     * @throws \Exception
     */
    public function execute(Observer $observer)
    {
        $configData = $observer->getData('configData');
        if (!isset($configData['section']) || $configData['section'] !== 'stape_gtm') {
            return;
        }

        $config = $this->configFactory->create();

        $storeId = $configData['store'] ?: null;
        $websiteId = $configData['website'] ?: null;

        $config->setScope(ScopeConfigInterface::SCOPE_TYPE_DEFAULT);
        $config->setScopeId(0);

        if (!empty(array_filter([$storeId, $websiteId]))) {
            $config->setScope($storeId ? ScopeInterface::SCOPE_STORE : ScopeInterface::SCOPE_WEBSITE);
            $config->setScopeId($storeId ?: $websiteId);
        }

        try {
            $snippet = $this->loaderApi->generateLoader(
                $this->resolveScope($config->getScope(), $config->getScopeId())
            );

            $config->setSection('stape_gtm');
            $config->setDataByPath(ConfigProvider::XPATH_GTM_SNIPPET, $snippet ?: null);
            $config->save();
        } catch (\Exception $e) {
            $this->logger->debug(sprintf('[STAPE] Could not generate snippet. Error: %s', $e->getMessage()));
        }
    }
}
