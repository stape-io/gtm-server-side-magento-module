<?php

namespace Stape\Gtm\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class ConfigProvider
{
    /*
     * XPATH for module enabled
     */
    public const XPATH_GTM_ACTIVE = 'stape_gtm/general/active';

    /*
     * XPATH for GTM container id config value
     */
    public const XPATH_GTM_CONTAINER_ID = 'stape_gtm/general/container_id';

    /*
     * XPATH for GTM domain config value
     */
    public const XPATH_GTM_DOMAIN = 'stape_gtm/general/custom_domain';

    /*
     * XPATH for GTM Loader config value
     */
    public const XPATH_GTM_LOADER = 'stape_gtm/general/custom_loader';

    /*
     * XPATH for GTM Cookie Keeper config value
     */
    public const XPATH_GTM_KEEP_COOKIE = 'stape_gtm/general/cookie_keeper';

    /**
     * @var ScopeConfigInterface $scopeConfig
     */
    private $scopeConfig;

    /**
     * Define class dependencies
     *
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Check if module is active
     *
     * @param string $scopeCode
     * @return bool
     */
    public function isActive($scopeCode = null)
    {
        return $this->scopeConfig->isSetFlag(self::XPATH_GTM_ACTIVE, ScopeInterface::SCOPE_STORE, $scopeCode);
    }

    /**
     * Retrieve GTM container id
     *
     * @param string $scopeCode
     * @return string
     */
    public function getContainerId($scopeCode = null)
    {
        return $this->scopeConfig->getValue(self::XPATH_GTM_CONTAINER_ID, ScopeInterface::SCOPE_STORE, $scopeCode);
    }

    /**
     * Retrieve custom domain
     *
     * @param string $scopeCode
     * @return string|null
     */
    public function getCustomDomain($scopeCode = null)
    {
        return $this->scopeConfig->getValue(self::XPATH_GTM_DOMAIN, ScopeInterface::SCOPE_STORE, $scopeCode);
    }

    /**
     * Retrieve custom loader
     *
     * @param string $scopeCode
     * @return string|null
     */
    public function getCustomLoader($scopeCode = null)
    {
        return $this->scopeConfig->getValue(self::XPATH_GTM_LOADER, ScopeInterface::SCOPE_STORE, $scopeCode);
    }

    /**
     * Check if cookie keeper should be used
     *
     * @param string|null $scopeCode
     * @return bool
     */
    public function useCookieKeeper($scopeCode = null)
    {
        return $this->scopeConfig->isSetFlag(self::XPATH_GTM_KEEP_COOKIE, ScopeInterface::SCOPE_STORE, $scopeCode);
    }
}
