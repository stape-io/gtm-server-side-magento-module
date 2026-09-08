<?php

namespace Stape\Gtm\Model;

use Magento\Store\Api\Data\StoreInterface;
use Stape\Gtm\Model\SameOrigin\BasePath;
use Stape\Gtm\Model\SameOrigin\ServiceWorkerPatch;

/**
 * Single producer of the rendered GTM snippet HTML.
 */
class SnippetProvider
{
    /**
     * @var ConfigProvider $configProvider
     */
    private $configProvider;

    /**
     * @var BasePath $basePath
     */
    private $basePath;

    /**
     * @var ServiceWorkerPatch $serviceWorkerPatch
     */
    private $serviceWorkerPatch;

    /**
     * Rendered snippet html, memoized per store scope
     *
     * @var array $snippetCache
     */
    private $snippetCache = [];

    /**
     * Define class dependencies
     *
     * @param ConfigProvider $configProvider
     * @param BasePath $basePath
     * @param ServiceWorkerPatch $serviceWorkerPatch
     */
    public function __construct(
        ConfigProvider $configProvider,
        BasePath $basePath,
        ServiceWorkerPatch $serviceWorkerPatch
    ) {
        $this->configProvider = $configProvider;
        $this->basePath = $basePath;
        $this->serviceWorkerPatch = $serviceWorkerPatch;
    }

    /**
     * Retrieve the rendered GTM snippet html for the given store
     *
     * Patched for same-origin proxying when the same-origin proxy is configured for the
     * store. Memoized per store scope since both Block\Gtm and Plugin\CspObserverPlugin call
     * this on the same request.
     *
     * @param StoreInterface|string|int|null $store
     * @return string
     */
    public function getHtml($store = null)
    {
        $key = $store instanceof StoreInterface ? $store->getCode() : (string) $store;

        if (array_key_exists($key, $this->snippetCache)) {
            return $this->snippetCache[$key];
        }

        return $this->snippetCache[$key] = $this->buildHtml($store);
    }

    /**
     * Build the rendered GTM snippet html for the given store
     *
     * @param StoreInterface|string|int|null $store
     * @return string
     */
    private function buildHtml($store)
    {
        $snippet = $this->configProvider->getGtmSnippet($store);

        if ($snippet === '' || !$this->configProvider->isSameOriginConfigured($store)) {
            return $snippet;
        }

        return $this->serviceWorkerPatch->prependTo($snippet, $this->basePath->get($store));
    }
}
