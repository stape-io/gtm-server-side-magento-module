<?php

namespace Stape\Gtm\Setup\Patch\Data;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
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

    private function updateConfig($scopeCode)
    {
        if ($customLoader = $this->configProvider->getCustomLoader($scopeCode)) {
            /** @var \Magento\Framework\App\Config\Value $configValue */
            $configValue = $this->customLoaderFactory->create()
                ->setPath(ConfigProvider::XPATH_GTM_LOADER)
                ->setValue($customLoader)
                ->setScope($scopeCode ?? ScopeConfigInterface::SCOPE_TYPE_DEFAULT);

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

        foreach ($stores as $store) {
            if ($this->updateConfig($store->getCode())) {
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
