<?php

namespace Stape\Gtm\Block;

use Magento\Framework\View\Element\Template;
use Psr\Http\Message\UriFactoryInterface;
use Stape\Gtm\Model\ConfigProvider;
use Stape\Gtm\Model\Datalayer\Formatter\Event as EventFormatter;
use Stape\Gtm\Model\SameOrigin\BasePath;
use Stape\Gtm\Model\SnippetProvider;

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
     * @var BasePath $basePath
     */
    private $basePath;

    /**
     * @var SnippetProvider $snippetProvider
     */
    private $snippetProvider;

    /**
     * @var UriFactoryInterface $uriFactory
     */
    private $uriFactory;

    /**
     * Define class dependencies
     *
     * @param Template\Context $context
     * @param ConfigProvider $configProvider
     * @param EventFormatter $eventFormatter
     * @param BasePath $basePath
     * @param SnippetProvider $snippetProvider
     * @param UriFactoryInterface $uriFactory
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        ConfigProvider $configProvider,
        EventFormatter $eventFormatter,
        BasePath $basePath,
        SnippetProvider $snippetProvider,
        UriFactoryInterface $uriFactory,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->configProvider = $configProvider;
        $this->eventFormatter = $eventFormatter;
        $this->basePath = $basePath;
        $this->snippetProvider = $snippetProvider;
        $this->uriFactory = $uriFactory;
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
        if ($this->configProvider->isSameOriginConfigured()) {
            return $this->getSameOriginBaseUrl();
        }

        return trim($this->configProvider->getCustomDomain() ?: 'https://www.googletagmanager.com', '/');
    }

    /**
     * Retrieve effective GTM container URL for same-origin mode (store base URL origin + proxy path)
     *
     * Built from the store base URL's origin rather than the full base URL, so it always agrees
     * with the base path the API-generated loader was built against.
     *
     * @return string
     */
    private function getSameOriginBaseUrl()
    {
        $store = $this->getCurrentStore();

        if ($store === null) {
            // root-relative is valid here: the proxy is same-origin by definition
            return $this->basePath->get($store);
        }

        try {
            $origin = $this->uriFactory
                ->createUri((string) $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB))
                ->withPath('')
                ->withQuery('')
                ->withFragment('');

            return rtrim((string) $origin, '/') . $this->basePath->get($store);
        } catch (\Exception $e) {
            // root-relative is valid here: the proxy is same-origin by definition
            return $this->basePath->get($store);
        }
    }

    /**
     * Retrieve the current store, or null when it cannot be resolved
     *
     * @return \Magento\Store\Api\Data\StoreInterface|null
     */
    private function getCurrentStore()
    {
        try {
            return $this->_storeManager->getStore();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Retrieve loader
     *
     * @return string
     */
    public function getLoader()
    {
        if ($this->configProvider->isSameOriginConfigured()) {
            return $this->configProvider->getSameOriginIdentifier() ?: 'gtm';
        }

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
     * Retrieve the file extension the loader is requested with
     *
     * Same-origin requests use ".load" because a web server configured to serve ".js" from
     * disk answers it before PHP is reached; Controller\SameOrigin\Proxy maps it back to
     * ".js" upstream and returns a JavaScript content type.
     *
     * @return string
     */
    public function getLoaderExtension()
    {
        return $this->configProvider->isSameOriginConfigured() ? 'load' : 'js';
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
     * Check if display currency has to be used for amounts
     *
     * @return bool
     */
    public function useDisplayCurrency()
    {
        return $this->configProvider->isDisplayCurrencyUsed();
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
        $snippet = $this->snippetProvider->getHtml($this->getCurrentStore());
        if (!empty($snippet)) {
            return $snippet;
        }

        if ($this->useCookieKeeper()) {
            return $this->getChildHtml('stape.gtm.advanced');
        }

        return $this->getChildHtml('stape.gtm.default');
    }
}
