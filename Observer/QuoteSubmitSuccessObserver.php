<?php

namespace Stape\Gtm\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Stape\Gtm\Model\ConfigProvider;
use Stape\Gtm\Model\Data\Webhook\CookieList as WebhookCookies;
use Stape\Gtm\Model\Webhook\Adapter;

class QuoteSubmitSuccessObserver implements ObserverInterface
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

    /** @var string[]  */
    private $cookies = [];

    /**
     * Define class dependencies
     *
     * @param ConfigProvider $configProvider
     * @param Adapter $adapter
     * @param WebhookCookies $cookieList
     * @param CookieManagerInterface $cookieManager
     */
    public function __construct(
        ConfigProvider $configProvider,
        Adapter $adapter,
        WebhookCookies $cookieList,
        CookieManagerInterface $cookieManager
    ) {
        $this->configProvider = $configProvider;
        $this->adapter = $adapter;
        $this->cookieManager = $cookieManager;
        $this->cookies = $cookieList->getAll();
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
        $enabled = $this->configProvider->webhooksEnabled($order->getStoreId());
        $hookEnabled = $this->configProvider->isPurchaseWebhookEnabled($order->getStoreId());
        if ($enabled && $hookEnabled) {
            $this->adapter->purchase($order, $this->prepareAdditionalInfo());
        }
    }
}
