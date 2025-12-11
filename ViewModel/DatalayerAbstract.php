<?php

namespace Stape\Gtm\ViewModel;

use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\StoreManagerInterface;
use Stape\Gtm\Model\Datalayer\Formatter\Event as EventFormatter;
use Stape\Gtm\Model\Datalayer\Modifier\PoolInterface;

abstract class DatalayerAbstract implements DatalayerInterface
{
    /**
     * @var Json
     */
    protected $json;

    /**
     * @var PriceCurrencyInterface $priceCurrency
     */
    protected $priceCurrency;

    /**
     * @var EventFormatter $eventFormatter
     */
    protected $eventFormatter;

    /**
     * @var StoreManagerInterface $storeManager
     */
    protected $storeManager;

    /**
     * @var PoolInterface|null $modifierPool
     */
    protected $modifierPool;

    /**
     * @param Json $json
     * @param EventFormatter $eventFormatter
     * @param StoreManagerInterface $storeManager
     * @param PriceCurrencyInterface $priceCurrency
     * @param PoolInterface|null $modifierPool
     */
    public function __construct(
        Json $json,
        EventFormatter $eventFormatter,
        StoreManagerInterface $storeManager,
        PriceCurrencyInterface $priceCurrency,
        ?PoolInterface $modifierPool = null
    ) {
        $this->eventFormatter = $eventFormatter;
        $this->json = $json;
        $this->priceCurrency = $priceCurrency;
        $this->storeManager = $storeManager;
        $this->modifierPool = $modifierPool;
    }

    /**
     * Retrieve json
     *
     * @return bool|string
     */
    public function getJson()
    {
        $eventData = $this->getEventData();

        if ($this->modifierPool) {
            foreach ($this->modifierPool->getModifiersInstances() as $modifier) {
                $eventData = $modifier->modifyEventData($eventData);
            }
        }

        return $this->json->serialize($eventData);
    }
}
