<?php

namespace Stape\Gtm\Setup\Patch\Data;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\ScopeInterface;
use Stape\Gtm\Model\Backend\CustomLoaderFactory;
use Stape\Gtm\Model\ConfigProvider;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class EnableSegmentation.
 *
 * @package Magento\Catalog\Setup\Patch
 */
class PatchCustomLoader implements DataPatchInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * @var ConfigProvider $configProvider
     */
    private $configProvider;

    /**
     * @var CustomLoaderFactory $customLoaderFactory
     */
    private $customLoaderFactory;

    /**
     * @var StoreManagerInterface $storeManager
     */
    private $storeManager;

    /**
     * Define class dependencies
     *
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param ConfigProvider $configProvider
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        ConfigProvider $configProvider,
        CustomLoaderFactory $configValueFactory,
        StoreManagerInterface $storeManager
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->configProvider = $configProvider;
        $this->customLoaderFactory = $configValueFactory;
        $this->storeManager = $storeManager;
    }

    /**
     * Update config
     *
     * @param \Magento\Store\Api\Data\StoreInterface $store
     * @return bool
     */
    private function updateConfig($store)
    {
        if ($customLoader = $this->configProvider->getCustomLoader($store->getCode())) {

            $scopeType = $store->getCode() !== 'admin'
                ? ScopeInterface::SCOPE_STORE : ScopeConfigInterface::SCOPE_TYPE_DEFAULT;

            /** @var \Magento\Framework\App\Config\Value $configValue */
            $configValue = $this->customLoaderFactory->create()
                ->setPath(ConfigProvider::XPATH_GTM_LOADER)
                ->setValue($customLoader)
                ->setScope($scopeType);

            if ($scopeType !== ScopeConfigInterface::SCOPE_TYPE_DEFAULT) {
                $configValue->setScopeId($store->getId());
            }

            $configValue->afterSave();
            return true;
        }
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function apply()
    {
        $this->moduleDataSetup->startSetup();

        $stores = $this->storeManager->getStores(true);

        /** @var \Magento\Store\Api\Data\StoreInterface $store */
        foreach ($stores as $store) {
            if ($this->updateConfig($store)) {
                break;
            }
        }

        $this->moduleDataSetup->endSetup();
    }

    /**
     * {@inheritdoc}
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getAliases()
    {
        return [];
    }
}
