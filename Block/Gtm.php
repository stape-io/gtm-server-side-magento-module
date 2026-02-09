<?php

namespace Stape\Gtm\Block;

use Magento\Framework\View\Element\Template;
use Stape\Gtm\Model\ConfigProvider;
use Stape\Gtm\Model\Datalayer\Formatter\Event as EventFormatter;

class Gtm extends \Magento\Framework\View\Element\Template
{
    /**
     * Config provider model
     *
     * @var ConfigProvider $configProvider
     */
    protected $configProvider;

    /**
     * @var EventFormatter $eventFormatter
     */
    protected $eventFormatter;

    /**
     * Define class dependencies
     *
     * @param Template\Context $context
     * @param ConfigProvider $configProvider
     * @param EventFormatter $eventFormatter
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        ConfigProvider $configProvider,
        EventFormatter $eventFormatter,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->configProvider = $configProvider;
        $this->eventFormatter = $eventFormatter;
    }

    /**
     * Retrieve container id
     *
     * @return string
     */
    protected function getStapeContainerId()
    {
        $params = $this->configProvider->getContainerIdParams();
        $containerId = urldecode(http_build_query([
            'id' => $this->_escaper->escapeHtml($this->configProvider->getContainerId())
        ]));

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
     * Retrieve formatted event name
     *
     * @return string
     */
    public function getEventSuffix()
    {
        return $this->configProvider->isStapeEventSuffixActive() ? EventFormatter::STAPE_EVENT_SUFFIX : '';
    }

    /**
     * Retrieve GTM snippet html
     *
     * @return string
     */
    public function getGtmSnippetHtml()
    {
        $snippet = $this->configProvider->getGtmSnippet();
        if (!empty($snippet)) {
            return $snippet;
        }

        if ($this->useCookieKeeper()) {
            return $this->getChildHtml('stape.gtm.advanced');
        }

        return $this->getChildHtml('stape.gtm.default');
    }
}
