<?php

namespace Stape\Gtm\Observer;

use Magento\Checkout\Model\Session;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Stape\Gtm\Model\ConfigProvider;

class QuoteSubmitSuccessObserver implements ObserverInterface
{
    /**
     * @var ConfigProvider $configProvider
     */
    private $configProvider;

    /**
     * @var Session $checkoutSession
     */
    private $checkoutSession;

    /**
     * Define class dependencies
     *
     * @param ConfigProvider $configProvider
     * @param Session $checkoutSession
     */
    public function __construct(
        ConfigProvider $configProvider,
        Session $checkoutSession
    ) {
        $this->configProvider = $configProvider;
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * Send webhook for purchase event
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        if ($this->configProvider->ecommerceEventsEnabled()) {
            $this->checkoutSession->unsetData('stape_cart_id');
        }
    }
}
