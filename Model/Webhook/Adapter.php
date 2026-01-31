<?php

namespace Stape\Gtm\Model\Webhook;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\ClientFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;
use Stape\Gtm\Model\Api\Client;
use Stape\Gtm\Model\Api\Request\RequestInterfaceFactory;
use Stape\Gtm\Model\ConfigProvider;
use Stape\Gtm\Model\Data\Converter;

class Adapter
{

    /**
     * @var Client
     */
    private $client;

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
     * @var RequestInterfaceFactory $requestFactory
     */
    private $requestFactory;

    /**
     * Define class dependencies
     *
     * @param Client $client
     * @param ConfigProvider $configProvider
     * @param LoggerInterface $logger
     * @param Converter $converter
     * @param RequestInterfaceFactory $requestFactory
     */
    public function __construct(
        Client                  $client,
        ConfigProvider          $configProvider,
        LoggerInterface         $logger,
        Converter               $converter,
        RequestInterfaceFactory $requestFactory,
    ) {
        $this->client = $client;
        $this->configProvider = $configProvider;
        $this->logger = $logger;
        $this->converter = $converter;
        $this->requestFactory = $requestFactory;
    }

    /**
     * Init request
     *
     * @param string|int|null $scope
     * @return \Stape\Gtm\Model\Api\Request\RequestInterface
     */
    protected function createRequest($scope = null)
    {
        return $this->requestFactory->create()
            ->setUrl($this->configProvider->getWebhooksUrl($scope));
    }

    /**
     * Execute API Call to Stape service
     *
     * @param string $event
     * @param array $data
     * @param string|null $scope
     * @return bool
     */
    protected function call($event, $data, $scope = null)
    {
        $data['event'] = $event;
        /** @var \Stape\Gtm\Model\Api\Request\RequestInterface $request */
        $request = $this->createRequest($scope)
            ->setUrl($this->configProvider->getWebhooksUrl($scope))
            ->setData($data);

        try {

            if (empty($request->getUrl())) {
                throw new LocalizedException(__('GTM server container URL must not be empty.'));
            }

            $result = $this->client->post($request);
            return $result->getStatus() === 200;
        } catch (\Exception $e) {
            $this->logger->error(sprintf('[STAPE WEBHOOK %s] %s', $event, $e->getMessage()));
            return false;
        }
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
                'payment_type' => $order->getPayment()->getMethodInstance()->getTitle(),
            ] + $additionalInfo;

        if ($order->getShippingAddress()) {
            $data['shipping_tier'] = $order->getShippingDescription();
        }

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
     * Void
     *
     * @param Order $order
     * @return void
     */
    public function void(\Magento\Sales\Model\Order $order)
    {
        $data = [
            'user_data' => $this->converter->orderToUserData($order),
            'ecommerce' => $this->converter->orderToEcomData($order)
        ];
        $this->call('refund_stape_webhook', $data, $order->getStoreId());
    }

    /**
     * Make test api call
     *
     * @return bool
     */
    public function test()
    {
        $data = [
            'user_data' => [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'example@example.com',
                'phone' => '1 800 444 4444',
                'customer_id' => 1010,
                'country' => 'US',
                'region' => 'VA',
                'street' => '752 Kildeer Drive',
                'city' => 'Norfolk',
                'zip' => '23513',
                'new_customer' => false
            ],
            'ecommerce' => [
                'currency' => 'USD',
                'transaction_id' => '10000001',
                'affiliation' => 'Test Web Hook Store',
                'value' => 115,
                'tax' => 10, // tax
                'shipping' => 5, // shipping price
                'coupon' => '', // coupon if exists
                'discount_amount' => 0, //
                'items' => [
                    [
                        'item_id' => 1001,
                        'item_name' => 'Test Product',
                        'item_category' => 'Test Product Category',
                        'price' => 100,
                        'quantity' => 1,
                        'item_sku' => 'test-product-sku',
                        'variation_id' => 1002,
                    ]
                ],
            ]
        ];

        return $this->call('test_stape_webhook', $data);
    }
}
