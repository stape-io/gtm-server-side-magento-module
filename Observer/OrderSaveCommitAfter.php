<?php

namespace Stape\Gtm\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Psr\Log\LoggerInterface;
use Stape\Gtm\Model\ConfigProvider;
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
     * @var LoggerInterface $logger
     */
    private $logger;

    /** @var string[]  */
    private $cookies = [
        '_fbc',
        '_fbp',
        'FPGCLAW',
        '_gcl_aw',
        'ttclid'
    ];

    /**
     * Define class dependencies
     *
     * @param ConfigProvider $configProvider
     * @param Adapter $adapter
     * @param CookieManagerInterface $cookieManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        ConfigProvider $configProvider,
        Adapter $adapter,
        CookieManagerInterface $cookieManager,
        LoggerInterface $logger
    ) {
        $this->configProvider = $configProvider;
        $this->adapter = $adapter;
        $this->cookieManager = $cookieManager;
        $this->logger = $logger;
    }

    /**
     * Preparing additional information
     *
     * @return array
     */
    private function prepareAdditionalInfo()
    {
        $additionalInformation = [];

        foreach ($this->cookies as $cookieName) {
            $additionalInformation['cookies'][$cookieName] = $this->cookieManager->getCookie($cookieName);
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

        try {
            $this->adapter->purchase($order, $this->prepareAdditionalInfo());
        } catch (\Exception $e) {
            $this->logger->notice($e->getMessage());
        }
    }
}
