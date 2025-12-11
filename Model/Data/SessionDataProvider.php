<?php

namespace Stape\Gtm\Model\Data;

use Magento\Checkout\Model\Session;
use Stape\Gtm\Model\Datalayer\Formatter\Event as EventFormatter;
use Stape\Gtm\Model\Datalayer\Modifier\PoolInterface;

class SessionDataProvider implements DataProviderInterface
{
    /**
     * @var Session $checkoutSession
     */
    private $checkoutSession;

    /**
     * @var EventFormatter $eventFormatter
     */
    private $eventFormatter;

    /**
     * @var PoolInterface $modifiersPool
     */
    private $modifiersPool;

    /**
     * Define class dependencies
     *
     * @param Session $checkoutSession
     * @param EventFormatter $eventFormatter
     * @param PoolInterface $modifiersPool
     */
    public function __construct(
        Session $checkoutSession,
        EventFormatter $eventFormatter,
        PoolInterface $modifiersPool
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->eventFormatter = $eventFormatter;
        $this->modifiersPool = $modifiersPool;
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
        foreach ($this->modifiersPool->getModifiersInstances() as $modifier) {
            $data = $modifier->modifyEventData($data);
        }
        $gtmEvents[$this->eventFormatter->formatName($eventName)] = $data;
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
