<?php

namespace Stape\Gtm\Model\Api;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\ClientFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Stape\Gtm\Model\Api\Request\RequestInterface;
use Stape\Gtm\Model\ConfigProvider;

class Client
{
    /*
     * Module version
     */
    public const MODULE_VERSION = '1.0.37';

    /**
     * @var ClientFactory
     */
    protected $clientFactory;

    /**
     * @var ConfigProvider
     */
    protected $configProvider;

    /**
     * @var Json $json
     */
    protected $json;

    /**
     * Define class dependencies
     *
     * @param ClientFactory $clientFactory
     * @param ConfigProvider $configProvider
     * @param Json $json
     */
    public function __construct(
        ClientFactory $clientFactory,
        ConfigProvider $configProvider,
        Json $json
    ) {
        $this->clientFactory = $clientFactory;
        $this->configProvider = $configProvider;
        $this->json = $json;
    }

    /**
     * Retrieve client
     *
     * @return \Magento\Framework\HTTP\ClientInterface
     */
    protected function client()
    {
        $client = $this->clientFactory->create();
        $client->addHeader('Content-Type', 'application/json');
        $client->addHeader('Accept', 'application/json');
        $client->addHeader('x-stape-app-version', self::MODULE_VERSION);
        return $client;
    }

    /**
     * Send post request
     *
     * @param RequestInterface $request
     * @return \Magento\Framework\HTTP\ClientInterface
     * @throws LocalizedException
     */
    public function post(RequestInterface $request)
    {
        if (empty($request->getUrl())) {
            throw new LocalizedException(__('GTM server container URL must not be empty.'));
        }
        $client = $this->client();
        $client->post($request->getUrl(), $this->json->serialize($request->getData()));
        return $client;
    }
}
