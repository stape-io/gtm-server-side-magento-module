<?php

namespace Stape\Gtm\Model\Data;

class SessionDataProvider implements DataProviderInterface
{

    private $checkoutSession;

    /**
     * Define class dependencies
     *
     * @param \Magento\Checkout\Model\Session $checkoutSession
     */
    public function __construct(\Magento\Checkout\Model\Session $checkoutSession)
    {
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * Retrieve Stape GTM events data
     *
     * @return array
     */
    public function get()
    {
        return $this->checkoutSession->getStapeGtmEvents() ?? [];
    }

    /**
     * Add gtm events data
     *
     * @param string $eventName
     * @param array $data
     * @return void
     */
    public function add($eventName, $data)
    {
        $gtmEvents = $this->get();
        $gtmEvents[$eventName] = $data;
        $this->checkoutSession->setStapeGtmEvents($gtmEvents);
    }

    /**
     * Clear gtm events
     *
     * @return void
     */
    public function clear()
    {
        $this->checkoutSession->setStapeGtmEvents([]);
    }
}
