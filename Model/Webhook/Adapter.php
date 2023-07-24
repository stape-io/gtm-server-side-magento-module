<?php

namespace Stape\Gtm\Model\Webhook;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\HTTP\ClientFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;
use Stape\Gtm\Model\ConfigProvider;
use Stape\Gtm\Model\Data\Converter;

class Adapter
{

    /**
     * @var Json $json
     */
    private $json;

    /**
     * @var ClientFactory $clientFactory
     */
    private $clientFactory;

    /**
     * @var ConfigProvider $configProvider
     */
    private $configProvider;

    /**
     * @var LoggerInterface $logger
     */
    private $logger;

    /**
     * @var Converter $converter
     */
    private $converter;

    /**
     * Define class dependencies
     *
     * @param Json $json
     * @param ClientFactory $clientFactory
     * @param ConfigProvider $configProvider
     * @param LoggerInterface $logger
     * @param Converter $converter
     */
    public function __construct(
        Json $json,
        ClientFactory $clientFactory,
        ConfigProvider $configProvider,
        LoggerInterface $logger,
        Converter $converter
    ) {
        $this->json = $json;
        $this->clientFactory = $clientFactory;
        $this->configProvider = $configProvider;
        $this->logger = $logger;
        $this->converter = $converter;
    }

    /**
     * Make request
     *
     * @param string $event
     * @param array $data
     * @param string $scopeCode
     * @return bool
     */
    private function call($event, $data, $scopeCode = null)
    {
        $data['event'] = $event;
        $client = $this->clientFactory->create();
        $client->addHeader('Content-Type', 'application/json');
        $client->addHeader('Accept', 'application/json');

        try {

            $url = $this->configProvider->getWebhooksUrl($scopeCode);
            if (empty($url)) {
                throw new LocalizedException(__('GTM server container URL must not be empty.'));
            }

            $client->post($url, $this->json->serialize($data));
        } catch (\Exception $e) {
            $this->logger->error(sprintf('[STAPE WEBHOOK %s] %s', $event, $e->getMessage()));
        }
        return $client->getStatus() == 200;
    }

    /**
     * Prepare and make purchase webhook call
     *
     * @param Order $order
     * @param array $additionalInfo
     * @return void
     */
    public function purchase(Order $order, array $additionalInfo = [])
    {
        $data = [
            'user_data' => $this->converter->orderToUserData($order),
            'ecommerce' => $this->converter->orderToEcomData($order),
        ] + $additionalInfo;
        $this->call('purchase_stape_webhook', $data, $order->getStoreId());
    }

    /**
     * Send webhook on refund
     *
     * @param Order\Creditmemo $creditmemo
     * @return void
     */
    public function refund(\Magento\Sales\Model\Order\Creditmemo $creditmemo)
    {
        $data = [
            'user_data' => $this->converter->orderToUserData($creditmemo->getOrder()),
            'ecommerce' => $this->converter->creditMemoToEcom($creditmemo),
        ];
        $this->call('refund_stape_webhook', $data, $creditmemo->getStoreId());
    }

    /**
     * Make test api call
     *
     * @return bool
     */
    public function test()
    {
        $data = [
            'firstname' => 'Test',
            'lastname' => 'Test'
        ];

        return $this->call('test_stape_webhook', $data);
    }
}
