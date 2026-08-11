<?php

namespace Stape\Gtm\Plugin\CustomerData;

use Magento\Checkout\Model\Session;
use Stape\Gtm\Model\Data\DataProviderInterface;
use Stape\Gtm\Model\Datalayer\Modifier\PoolInterface;
use Stape\Gtm\Model\Price\CurrencyResolver;
use Stape\Gtm\Model\Price\Totals;

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
     * @var Totals $totals
     */
    private $totals;

    /**
     * @var CurrencyResolver $currencyResolver
     */
    private $currencyResolver;

    /**
     * Define class dependencies
     *
     * @param DataProviderInterface $dataProvider
     * @param PoolInterface $modifiersPool
     * @param Session $session
     * @param Totals $totals
     * @param CurrencyResolver $currencyResolver
     */
    public function __construct(
        DataProviderInterface $dataProvider,
        PoolInterface $modifiersPool,
        Session $session,
        Totals $totals,
        CurrencyResolver $currencyResolver
    ) {
        $this->dataProvider = $dataProvider;
        $this->modifiersPool = $modifiersPool;
        $this->checkoutSession = $session;
        $this->totals = $totals;
        $this->currencyResolver = $currencyResolver;
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

        // Resolved server side so JS never has to pick between base and display currency.
        try {
            $quote = $this->checkoutSession->getQuote();
            $result['stape_cart_value'] = $this->totals->forEntity($quote, Totals::FIELD_GRAND_TOTAL);
            $result['stape_currency'] = $this->currencyResolver->codeForQuote($quote);
        } catch (\Throwable $e) {
            $result['stape_cart_value'] = null;
            $result['stape_currency'] = null;
        }

        if (!empty($eventsData)) {
            $result['stape_gtm_events'] = $eventsData;
        }

        return $result;
    }
}
