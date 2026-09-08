<?php

namespace Stape\Gtm\Model\Api;

use Magento\Framework\DataObjectFactory;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\StoreManagerInterface;
use Stape\Gtm\Model\Api\Request\RequestInterfaceFactory;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerInterface;
use Stape\Gtm\Model\Api\Request\RequestInterface;
use Stape\Gtm\Model\ConfigProvider;
use Stape\Gtm\Model\SameOrigin\BasePath;

class Loader
{
    /*
     * GLOBAL API BASE URL
     */
    private const BASE_URL = 'https://api.app.stape.io/api/v2';

    /*
     * EU API URL (fallback for EU containers that 404 on the global endpoint)
     */
    private const EU_BASE_URL = 'https://api.app.eu.stape.io/api/v2';

    /**
     * @var Client $client
     */
    private $client;

    /**
     * @var ConfigProvider $configProvider
     */
    private $configProvider;

    /**
     * @var RequestInterfaceFactory $requestFactory
     */
    private $requestFactory;

    /**
     * @var LoggerInterface $logger
     */
    private $logger;

    /**
     * @var Json $json
     */
    private $json;

    /**
     * @var DataObjectFactory $dataObjectFactory
     */
    private $dataObjectFactory;

    /**
     * @var StoreManagerInterface $storeManager
     */
    private $storeManager;

    /**
     * @var UriFactoryInterface $uriFactory
     */
    private $uriFactory;

    /**
     * @var BasePath $basePath
     */
    private $basePath;

    /**
     * Define class dependencies
     *
     * @param Client $client
     * @param ConfigProvider $configProvider
     * @param RequestInterfaceFactory $requestFactory
     * @param Json $json
     * @param DataObjectFactory $dataObjectFactory
     * @param StoreManagerInterface $storeManager
     * @param UriFactoryInterface $uriFactory
     * @param LoggerInterface $logger
     * @param BasePath $basePath
     */
    public function __construct(
        Client $client,
        ConfigProvider $configProvider,
        RequestInterfaceFactory $requestFactory,
        Json $json,
        DataObjectFactory $dataObjectFactory,
        StoreManagerInterface $storeManager,
        UriFactoryInterface $uriFactory,
        LoggerInterface $logger,
        BasePath $basePath
    ) {
        $this->client = $client;
        $this->configProvider = $configProvider;
        $this->requestFactory = $requestFactory;
        $this->json = $json;
        $this->logger = $logger;
        $this->dataObjectFactory = $dataObjectFactory;
        $this->storeManager = $storeManager;
        $this->uriFactory = $uriFactory;
        $this->basePath = $basePath;
    }

    /**
     * Retrieve endpoint
     *
     * @param string $endpoint
     * @param string $baseUrl
     * @return string
     */
    protected function getUrl($endpoint, $baseUrl = self::BASE_URL)
    {
        return sprintf('%s/%s', $baseUrl, $endpoint);
    }

    /**
     * Create request object
     *
     * @param string $identifier
     * @param string $baseUrl
     * @return RequestInterface
     */
    private function createRequest($identifier, $baseUrl = self::BASE_URL)
    {
        return $this->requestFactory->create()->setUrl(
            $this->getUrl(
                sprintf('container/%s/custom-loader', $identifier),
                $baseUrl
            )
        );
    }

    /**
     * Create URI object
     *
     * @param string $uri
     * @return UriInterface|null
     */
    private function createUri($uri)
    {
        try {
            return $this->uriFactory->createUri($uri);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Resolve store for the given config scope
     *
     * @param mixed $scope store, website or null (default scope)
     * @return \Magento\Store\Api\Data\StoreInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function resolveStore($scope)
    {
        if ($scope instanceof \Magento\Store\Api\Data\StoreInterface) {
            return $scope;
        }

        if ($scope instanceof \Magento\Store\Model\Website) {
            return $scope->getDefaultStore();
        }

        return $this->storeManager->getDefaultStoreView();
    }

    /**
     * Retrieve host of the store base URL for the given scope
     *
     * The custom-loader API rejects hosts with a port, so only the bare host is returned.
     *
     * @param mixed $scope
     * @return string|null
     */
    private function getStoreHost($scope)
    {
        try {
            $baseUrl = $this->resolveStore($scope)->getBaseUrl();
        } catch (\Exception $e) {
            return null;
        }

        $uri = $this->createUri($baseUrl);

        return $uri && $uri->getHost() !== '' ? $uri->getHost() : null;
    }

    /**
     * Build request data for the default (custom domain) mode
     *
     * @param string|int $scope
     * @return array
     */
    private function buildDefaultRequestData($scope)
    {
        $requestData = [
            'webGtmId' => $this->configProvider->getContainerId($scope),
            'source' => 'magento',
            'dataLayerObjectName' => 'dataLayer',
        ];

        $uri = $this->createUri($this->configProvider->getCustomDomain($scope) ?? '');

        if ($uri && $uri->getHost()) {
            $requestData['domain'] = $uri->getHost();
        }

        if ($uri && $uri->getPath()) {
            $requestData['sameOriginPath'] = $uri->getPath();
        }

        if ($this->configProvider->useCookieKeeper($scope)) {
            $requestData['userIdentifierType'] = 'cookie';
            $requestData['userIdentifierValue'] = '_sbp';
        }

        return $requestData;
    }

    /**
     * Build request data for the same-origin proxy mode
     *
     * Cookie Keeper identifiers are intentionally never sent in this mode.
     *
     * @param string|int $scope
     * @return array
     */
    private function buildSameOriginRequestData($scope)
    {
        $requestData = [
            'webGtmId' => $this->configProvider->getContainerId($scope),
            'source' => 'magento',
            'dataLayerObjectName' => 'dataLayer',
            'sameOriginPath' => $this->basePath->get($scope),
        ];

        if ($host = $this->getStoreHost($scope)) {
            $requestData['domain'] = $host;
        }

        return $requestData;
    }

    /**
     * Rewrite the loader extension from ".js" to ".load"
     *
     * Web servers often serve ".js" paths as static files, so the same-origin loader is
     * requested with the ".load" extension and mapped back by the proxy controller.
     *
     * The match is anchored to a URL whose first path segment is the configured proxy
     * path, so it cannot touch the GTM bootstrap event name ("gtm.js") or third-party
     * URLs that merely contain the proxy path further down their own path.
     *
     * @param string $jsCode
     * @param string|int $scope
     * @return string
     */
    private function rewriteLoaderExtension($jsCode, $scope)
    {
        $path = $this->basePath->get($scope);

        if ($path === '') {
            return $jsCode;
        }

        $pattern = '#((?:https?:)?//[^/"\'\s?]+'
            . preg_quote($path, '#')
            . '(?:/[A-Za-z0-9._~-]+)+)\.js(?=[?"\'])#i';

        return preg_replace($pattern, '$1.load', $jsCode);
    }

    /**
     * Generate GTM code snippet
     *
     * @param string|int $scope
     * @return string|null
     */
    public function generateLoader($scope = null)
    {
        $sameOrigin = $this->configProvider->isSameOriginConfigured($scope);

        if ($sameOrigin) {
            $identifier = $this->configProvider->getSameOriginIdentifier($scope);
            $requestData = $this->buildSameOriginRequestData($scope);
        } else {
            $identifier = $this->configProvider->getCustomLoader($scope);
            $requestData = $this->buildDefaultRequestData($scope);
        }

        if (empty($identifier)) {
            return null;
        }

        try {

            $result = $this->client->post($this->createRequest($identifier)->setData($requestData));

            // EU containers 404 on the global endpoint; retry against the EU endpoint before falling back.
            if ($result->getStatus() === 404) {
                $result = $this->client->post(
                    $this->createRequest($identifier, self::EU_BASE_URL)->setData($requestData)
                );
            }

            /** @var \Magento\Framework\DataObject $response */
            $response = $this->dataObjectFactory->create([
                'data' => $this->json->unserialize($result->getBody() ?? '')
            ]);

            if ($result->getStatus() !== 200) {
                throw new NotFoundException(
                    __($response->getData('error/error')) ?? __('Could not generate GTM snippet')
                );
            }

            $jsCode = $response->getData('body/jsCode');

            if ($sameOrigin && is_string($jsCode)) {
                $jsCode = $this->rewriteLoaderExtension($jsCode, $scope);
            }

            return $jsCode;
        } catch (\Exception $e) {
            $this->logger->debug(sprintf('[STAPE] Could not generate GTM snippet. Error: %s', $e->getMessage()));
        }
        return null;
    }
}
