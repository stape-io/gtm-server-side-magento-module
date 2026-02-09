<?php

namespace Stape\Gtm\Model\Api;

use Magento\Framework\DataObjectFactory;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\Serialize\Serializer\Json;
use Stape\Gtm\Model\Api\Request\RequestInterfaceFactory;
use Psr\Log\LoggerInterface;
use Stape\Gtm\Model\Api\Request\RequestInterface;
use Stape\Gtm\Model\ConfigProvider;
use GuzzleHttp\Psr7\Utils;

class Loader
{
    /*
     * BASE API URL
     */
    private const BASE_URL = 'https://api.app.stape.io/api/v2';

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
     * Define class dependencies
     *
     * @param Client $client
     * @param ConfigProvider $configProvider
     * @param RequestInterfaceFactory $requestFactory
     * @param Json $json
     * @param DataObjectFactory $dataObjectFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        Client $client,
        ConfigProvider $configProvider,
        RequestInterfaceFactory $requestFactory,
        Json $json,
        DataObjectFactory $dataObjectFactory,
        LoggerInterface $logger
    ) {
        $this->client = $client;
        $this->configProvider = $configProvider;
        $this->requestFactory = $requestFactory;
        $this->json = $json;
        $this->logger = $logger;
        $this->dataObjectFactory = $dataObjectFactory;
    }

    /**
     * Retrieve endpoint
     *
     * @param string $endpoint
     * @return string
     */
    protected function getUrl($endpoint)
    {
        return sprintf('%s/%s', self::BASE_URL, $endpoint);
    }

    /**
     * Create request object
     *
     * @param string|null $scope
     * @return RequestInterface
     */
    private function createRequest($scope = null)
    {
        return $this->requestFactory->create()->setUrl(
            $this->getUrl(sprintf('container/%s/custom-loader', $this->configProvider->getCustomLoader($scope)))
        );
    }

    /**
     * Create URI object
     *
     * @param string $uri
     * @return \Psr\Http\Message\UriInterface|null
     */
    private function createUri($uri)
    {
        try {
            return Utils::uriFor($uri);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Generate GTM code snippet
     *
     * @param string|int $scope
     * @return string|null
     */
    public function generateLoader($scope = null)
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

        try {

            $request = $this->createRequest($scope)->setData($requestData);
            $result = $this->client->post($request);

            /** @var \Magento\Framework\DataObject $response */
            $response = $this->dataObjectFactory->create([
                'data' => $this->json->unserialize($result->getBody() ?? '')
            ]);

            if ($result->getStatus() !== 200) {
                throw new NotFoundException($response->getData('error/error') ?? 'Could not generate GTM snippet');
            }

            return $response->getData('body/jsCode');
        } catch (\Exception $e) {
            $this->logger->debug(sprintf('[STAPE] Could not generate GTM snippet. Error: %s', $e->getMessage()));
        }
        return null;
    }
}
