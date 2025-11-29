<?php

namespace Stape\Gtm\Plugin\CustomerData;

use Magento\Checkout\Model\Session;
use Stape\Gtm\Model\Data\DataProviderInterface;
use Stape\Gtm\Model\Datalayer\Modifier\PoolInterface;

class CartPlugin
{
    /**
     * @var DataProviderInterface
     */
    private $dataProvider;

    /**
     * @var PoolInterface $modifiersPool
     */
    private $modifiersPool;

    /**
     * @var Session $checkoutSession
     */
    private $checkoutSession;

    /**
     * Define class dependencies
     *
     * @param DataProviderInterface $dataProvider
     * @param PoolInterface $modifiersPool
     * @param Session $session
     */
    public function __construct(
        DataProviderInterface $dataProvider,
        PoolInterface $modifiersPool,
        Session $session
    ) {
        $this->dataProvider = $dataProvider;
        $this->modifiersPool = $modifiersPool;
        $this->checkoutSession = $session;
    }

    /**
     * Add stape_gtm_events data to the cart section data
     *
     * @param \Magento\Checkout\CustomerData\Cart $subject
     * @param array $result
     * @return array
     */
    public function afterGetSectionData($subject, $result)
    {
        $eventsData = $this->dataProvider->get();
        $this->dataProvider->clear();

        if (!$this->checkoutSession->getData('stape_cart_id')) {
            $this->checkoutSession->setData('stape_cart_id', bin2hex(random_bytes(16)));
        }

        $result['stape_cart_id'] = $this->checkoutSession->getData('stape_cart_id');

        if (!empty($eventsData)) {
            $result['stape_gtm_events'] = $eventsData;
        }

        return $result;
    }
}
