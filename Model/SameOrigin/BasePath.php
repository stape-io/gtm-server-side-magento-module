<?php

namespace Stape\Gtm\Model\SameOrigin;

use Magento\Framework\UrlInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Http\Message\UriFactoryInterface;
use Stape\Gtm\Model\ConfigProvider;

/**
 * Single definition of the browser-visible same-origin proxy base path.
 *
 * With the store at https://shop.example/store/ and the proxy path set to /gtm, the
 * browser requests https://shop.example/store/gtm/abc.load, and serving that needs two
 * different strings:
 *
 * - /gtm is what the router matches. Magento strips the base URL before routing, so
 *   PATH_INFO is already /gtm/abc.load and Controller\Router\SameOriginProxy compares
 *   the raw configured value against it.
 * - /store/gtm is what the browser needs, and what this class returns. It goes into
 *   generated loader URLs, and it is the prefix the injected service worker patch tests
 *   location.pathname against inside the document the proxy returns.
 *
 * Both are identical when the store sits at the domain root, because the base URL then
 * has no path component - which is why reading the configured value for both works
 * almost everywhere and breaks only on a sub-directory install, where a loader URL
 * missing the /store prefix points outside the application and a router given that
 * prefix matches nothing.
 */
class BasePath
{
    /**
     * @var ConfigProvider $configProvider
     */
    private $configProvider;

    /**
     * @var StoreManagerInterface $storeManager
     */
    private $storeManager;

    /**
     * @var UriFactoryInterface $uriFactory
     */
    private $uriFactory;

    /**
     * Define class dependencies
     *
     * @param ConfigProvider $configProvider
     * @param StoreManagerInterface $storeManager
     * @param UriFactoryInterface $uriFactory
     */
    public function __construct(
        ConfigProvider $configProvider,
        StoreManagerInterface $storeManager,
        UriFactoryInterface $uriFactory
    ) {
        $this->configProvider = $configProvider;
        $this->storeManager = $storeManager;
        $this->uriFactory = $uriFactory;
    }

    /**
     * Retrieve the browser-visible proxy base path for the given scope
     *
     * The result has a leading slash, no trailing slash, and no duplicate
     * slashes. Returns an empty string when no proxy path is configured for
     * the scope, or when the scope resolves to no store.
     *
     * @param StoreInterface|WebsiteInterface|string|int|null $scope Store, website, store code/id,
     *     or null. Null resolves to the default store view, NOT the current store — frontend
     *     callers must pass the current store explicitly.
     * @return string
     */
    public function get($scope = null)
    {
        $store = $this->resolveStore($scope);

        $path = trim((string) $this->configProvider->getSameOriginPath($store), '/');

        if ($path === '') {
            return '';
        }

        $prefix = trim($this->getStoreBasePath($store), '/');

        return rtrim((string) preg_replace('#/{2,}#', '/', '/' . $prefix . '/' . $path), '/');
    }

    /**
     * Retrieve the path component of the store's base URL
     *
     * @param StoreInterface|null $store
     * @return string
     */
    private function getStoreBasePath($store)
    {
        if ($store === null) {
            return '';
        }

        try {
            return $this->uriFactory->createUri((string) $store->getBaseUrl(UrlInterface::URL_TYPE_WEB))->getPath();
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Resolve the given scope into a store, or null when it cannot be resolved
     *
     * @param StoreInterface|WebsiteInterface|string|int|null $scope
     * @return StoreInterface|null
     */
    private function resolveStore($scope)
    {
        try {
            if ($scope instanceof StoreInterface) {
                return $scope;
            }

            if ($scope instanceof WebsiteInterface) {
                return $scope->getDefaultStore();
            }

            if ($scope === null) {
                return $this->storeManager->getDefaultStoreView();
            }

            return $this->storeManager->getStore($scope);
        } catch (\Exception $e) {
            return null;
        }
    }
}
