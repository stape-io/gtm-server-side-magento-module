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

    /*
     * XPATH for data layer e-commerce events
     */
    public const XPATH_ECOM_EVENTS_ENABLED = 'stape_gtm/datalayer/ecom';

    /*
     * XPATH for adding user data to e-com events
     */
    public const XPATH_USER_DATA_ENABLED = 'stape_gtm/datalayer/userdata';

    /*
     * XPATH to check if webhooks are enabled
     */
    public const XPATH_WEBHOOK_ACTIVE = 'stape_gtm/webhooks/active';

    /*
     * XPATH purchase webhook active or not
     */
    public const XPATH_WEBHOOK_PURCHASE_ACTIVE = 'stape_gtm/webhooks/purchase';

    /*
     * XPATH refund webhook active or not
     */
    public const XPATH_WEBHOOK_REFUND_ACTIVE = 'stape_gtm/webhooks/refund';

    /*
     * XPATH for GMT container URL
     */
    public const XPATH_WEBHOOK_GTM_CONTAINER_URL = 'stape_gtm/webhooks/gtm_container_url';

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

    /**
     * Check if datalayer e-commerce events tracking is enabled
     *
     * @param string}null $scopeCode
     * @return bool
     */
    public function ecommerceEventsEnabled($scopeCode = null)
    {
        return $this->scopeConfig->isSetFlag(self::XPATH_ECOM_EVENTS_ENABLED, ScopeInterface::SCOPE_STORE, $scopeCode);
    }

    /**
     * Check if user data should be added to datalayer e-commerce events
     *
     * @param string|null $scopeCode
     * @return bool
     */
    public function canAddUserData($scopeCode = null)
    {
        return $this->scopeConfig->isSetFlag(self::XPATH_USER_DATA_ENABLED, ScopeInterface::SCOPE_STORE, $scopeCode);
    }

    /**
     * Check if webhooks functionality is enabled
     *
     * @param string|null $scopeCode
     * @return bool
     */
    public function webhooksEnabled($scopeCode = null)
    {
        return $this->scopeConfig->isSetFlag(self::XPATH_WEBHOOK_ACTIVE, ScopeInterface::SCOPE_STORE, $scopeCode);
    }

    /**
     * Check if purchase webhook is enabled
     *
     * @param string|null $scopeCode
     * @return bool
     */
    public function isPurchaseWebhookEnabled($scopeCode = null)
    {
        return $this->scopeConfig->isSetFlag(
            self::XPATH_WEBHOOK_PURCHASE_ACTIVE,
            ScopeInterface::SCOPE_STORE,
            $scopeCode
        );
    }

    /**
     * Check if refund webhook is enabled
     *
     * @param string|null $scopeCode
     * @return bool
     */
    public function isRefundWebhookEnabled($scopeCode = null)
    {
        return $this->scopeConfig->isSetFlag(
            self::XPATH_WEBHOOK_REFUND_ACTIVE,
            ScopeInterface::SCOPE_STORE,
            $scopeCode
        );
    }

    /**
     * Retrieve webhooks url
     *
     * @param string|null $scopeCode
     * @return string
     */
    public function getWebhooksUrl($scopeCode = null)
    {
        return $this->scopeConfig->getValue(
            self::XPATH_WEBHOOK_GTM_CONTAINER_URL,
            ScopeInterface::SCOPE_STORE,
            $scopeCode
        );
    }
}
