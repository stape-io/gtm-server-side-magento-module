<?php

namespace Stape\Gtm\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Psr\Log\LoggerInterface;
use Stape\Gtm\Model\ConfigProvider;
use Stape\Gtm\Model\Data\Webhook\CookieList as WebhookCookies;
use Stape\Gtm\Model\CookieReader;
use Stape\Gtm\Model\Webhook\Adapter;

class OrderSaveCommitAfter implements ObserverInterface
{
    /**
     * @var ConfigProvider $configProvider
     */
    private $configProvider;

    /**
     * @var Adapter $adapter
     */
    private $adapter;

    /**
     * @var CookieManagerInterface $cookieManager
     */
    private $cookieManager;

    /**
     * @var OrderPaymentRepositoryInterface $orderPaymentRepository
     */
    private $orderPaymentRepository;

    /**
     * @var LoggerInterface $logger
     */
    private $logger;

    /**
     * @var WebhookCookies
     */
    private $cookieList;

    /**
     * @var CookieReader $cookieReader
     */
    private $cookieReader;

    /**
     * Define class dependencies
     *
     * @param ConfigProvider $configProvider
     * @param Adapter $adapter
     * @param CookieManagerInterface $cookieManager
     * @param OrderPaymentRepositoryInterface $orderPaymentRepository
     * @param WebhookCookies $cookieList
     * @param CookieReader $cookieReader
     * @param LoggerInterface $logger
     */
    public function __construct(
        ConfigProvider $configProvider,
        Adapter $adapter,
        CookieManagerInterface $cookieManager,
        OrderPaymentRepositoryInterface $orderPaymentRepository,
        WebhookCookies $cookieList,
        CookieReader $cookieReader,
        LoggerInterface $logger
    ) {
        $this->configProvider = $configProvider;
        $this->adapter = $adapter;
        $this->cookieManager = $cookieManager;
        $this->orderPaymentRepository = $orderPaymentRepository;
        $this->logger = $logger;
        $this->cookieList = $cookieList;
        $this->cookieReader = $cookieReader;
    }

    /**
     * Preparing additional information
     *
     * @return array
     */
    private function prepareAdditionalInfo()
    {
        $additionalInformation = [];

        foreach ($this->cookieReader->getAllCookies() as $name => $value) {
            if ($this->cookieList->isAllowedCookie($name)) {
                $additionalInformation['cookies'][$name] = $value;
            }
        }

        return $additionalInformation;
    }

    /**
     * Send webhook for purchase event
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $observer->getOrder();

        if (!$order->dataHasChangedFor('total_paid') || $order->getGrandTotal() > $order->getTotalPaid()) {
            return;
        }

        $enabled = $this->configProvider->webhooksEnabled($order->getStoreId());
        $hookEnabled = $this->configProvider->isPurchaseWebhookEnabled($order->getStoreId());

        if (!$enabled && !$hookEnabled) {
            return;
        }

        $payment = $order->getPayment();

        if ($payment->getAdditionalInformation('stape_purchase_webhook_processed')) {
            return;
        }

        try {
            $this->adapter->purchase($order, $this->prepareAdditionalInfo());
            $payment->setAdditionalInformation('stape_purchase_webhook_processed', true);
            $this->orderPaymentRepository->save($payment);
        } catch (\Exception $e) {
            $this->logger->notice($e->getMessage());
        }
    }
}
