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
     * Retrieve domain
     *
     * @return string
     */
    public function getDomain()
    {
        return $this->configProvider->getCustomDomain() ?: 'https://www.googletagmanager.com/';
    }

    /**
     * Retrieve loader
     *
     * @return string
     */
    public function getLoader()
    {
        return $this->configProvider->getCustomLoader() ?: 'gtm';
    }

    /**
     * Retrieve GTM container id
     *
     * @return string
     */
    public function getContainerId()
    {
        return $this->configProvider->getContainerId();
    }

    /**
     * Get GTM Url
     *
     * @return string
     */
    public function getGtmUrl()
    {
        return $this->getDomain() . $this->getLoader();
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
}
