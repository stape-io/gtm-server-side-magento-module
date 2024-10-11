<?php

namespace Stape\Gtm\Block;

use Magento\Framework\View\Element\Template;
use Stape\Gtm\Model\ConfigProvider;

class Gtm extends \Magento\Framework\View\Element\Template
{
    /**
     * Config provider model
     *
     * @var ConfigProvider $configProvider
     */
    protected $configProvider;

    /**
     * Define class dependencies
     *
     * @param Template\Context $context
     * @param ConfigProvider $configProvider
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        ConfigProvider $configProvider,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->configProvider = $configProvider;
    }

    /**
     * Retrieve container id
     *
     * @return string
     */
    protected function getStapeContainerId()
    {
        $params = $this->configProvider->getContainerIdParams();
        $containerId = urldecode(http_build_query(array_merge(
            ['id' => $this->_escaper->escapeHtml($this->configProvider->getContainerId())],
            $this->getAdditionalQueryParams()
        )));

        return http_build_query(array_merge([$params['prefix'] => base64_encode($containerId)], $params['suffix']));
    }

    /**
     * Retrieve domain
     *
     * @return string
     */
    public function getDomain()
    {
        return trim($this->configProvider->getCustomDomain() ?: 'https://www.googletagmanager.com', '/');
    }

    /**
     * Retrieve loader
     *
     * @return string
     */
    public function getLoader()
    {
        if (!$customLoader = $this->configProvider->getCustomLoader()) {
            return 'gtm';
        }

        return implode('', [$this->configProvider->getCustomLoaderPrefix(), $customLoader]);
    }

    /**
     * Retrieve GTM container id
     *
     * @return string
     */
    public function getContainerId()
    {
        if ($this->configProvider->getCustomLoader()) {
            return $this->getStapeContainerId();
        }

        return sprintf('id=%s', $this->_escaper->escapeJs($this->configProvider->getContainerId()));
    }

    /**
     * Get GTM Url
     *
     * @return string
     */
    public function getGtmUrl()
    {
        return implode('/', [
            $this->getDomain(),
            $this->getLoader()
        ]);
    }

    /**
     * Use cookie keeper
     *
     * @return bool
     */
    public function useCookieKeeper()
    {
        return $this->configProvider->useCookieKeeper();
    }

    /**
     * Check if datalayer is enabled
     *
     * @return bool
     */
    public function isDataLayerEnabled()
    {
        return $this->configProvider->isActive() && $this->configProvider->ecommerceEventsEnabled();
    }

    /**
     * Check if user data tracking is enabled
     *
     * @return bool
     */
    public function isUserDataEnabled()
    {
        return $this->configProvider->canAddUserData();
    }

    /**
     * Retrieve id param name
     *
     * @return string
     */
    public function getIdParamName()
    {
        return $this->configProvider->getCustomLoader() && $this->configProvider->getCustomDomain() ? 'st' : 'id';
    }

    /**
     * Retrieve analytics param
     *
     * @return string
     */
    public function getAnalyticsParam()
    {
        return $this->configProvider->isStapeAnalyticsEnabled() && !empty($this->configProvider->getCustomLoader())
            ? 'y' : '';
    }

    /**
     * Retrieve additional query params
     *
     * @return string[]
     */
    public function getAdditionalQueryParams()
    {
        return array_filter([
            'as' => $this->getAnalyticsParam(),
        ]);
    }
}
