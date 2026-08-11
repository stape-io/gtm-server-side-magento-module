<?php

namespace Stape\Gtm\Model\Price;

use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Store\Model\StoreManagerInterface;
use Stape\Gtm\Model\ConfigProvider;

/**
 * Resolves which currency the data layer reports amounts in.
 *
 * Honours the "Use Display Currency for Amounts" configuration flag: when the flag is
 * disabled (the default) amounts and currency codes use the website base currency,
 * when it is enabled they use the storefront display currency.
 *
 * Every currency code emitted by the module must come from this class so the code
 * always describes the currency the accompanying amounts are actually expressed in.
 */
class CurrencyResolver
{
    /**
     * @var ConfigProvider $configProvider
     */
    private $configProvider;

    /**
     * @var StoreManagerInterface $storeManager
     */
    private $storeManager;

    /**
     * @var PriceCurrencyInterface $priceCurrency
     */
    private $priceCurrency;

    /**
     * Define class dependencies
     *
     * @param ConfigProvider $configProvider
     * @param StoreManagerInterface $storeManager
     * @param PriceCurrencyInterface $priceCurrency
     */
    public function __construct(
        ConfigProvider $configProvider,
        StoreManagerInterface $storeManager,
        PriceCurrencyInterface $priceCurrency
    ) {
        $this->configProvider = $configProvider;
        $this->storeManager = $storeManager;
        $this->priceCurrency = $priceCurrency;
    }

    /**
     * Check if amounts have to be reported in the display currency
     *
     * @param string|int|null $scopeCode
     * @return bool
     */
    public function useDisplayCurrency($scopeCode = null)
    {
        return $this->configProvider->isDisplayCurrencyUsed($scopeCode);
    }

    /**
     * Retrieve currency code of a quote
     *
     * @param \Magento\Quote\Api\Data\CartInterface|\Magento\Quote\Model\Quote $quote
     * @param string|int|null $scopeCode
     * @return string|null
     */
    public function codeForQuote($quote, $scopeCode = null)
    {
        return $this->useDisplayCurrency($scopeCode)
            ? $quote->getQuoteCurrencyCode()
            : $quote->getBaseCurrencyCode();
    }

    /**
     * Retrieve currency code of an order
     *
     * @param \Magento\Sales\Api\Data\OrderInterface|\Magento\Sales\Model\Order $order
     * @param string|int|null $scopeCode
     * @return string|null
     */
    public function codeForOrder($order, $scopeCode = null)
    {
        return $this->useDisplayCurrency($scopeCode)
            ? $order->getOrderCurrencyCode()
            : $order->getBaseCurrencyCode();
    }

    /**
     * Retrieve currency code of a store
     *
     * @param string|int|null $scopeCode
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function codeForStore($scopeCode = null)
    {
        $store = $this->storeManager->getStore($scopeCode);

        return $this->useDisplayCurrency($scopeCode)
            ? $store->getCurrentCurrency()->getCode()
            : $store->getBaseCurrency()->getCode();
    }

    /**
     * Convert a base currency amount to the reported currency
     *
     * For amounts that are always calculated in base currency, such as the customer
     * lifetime spent, which only need converting when the display currency is reported.
     *
     * @param float|string|null $amount
     * @param string|int|null $scopeCode
     * @return float
     */
    public function convertFromBaseCurrency($amount, $scopeCode = null)
    {
        if (!$this->useDisplayCurrency($scopeCode)) {
            return (float) $amount;
        }

        return (float) $this->priceCurrency->convert((float) $amount, $scopeCode);
    }

    /**
     * Convert a display currency amount back to the base currency
     *
     * Needed for values the pricing layer only exposes in the display currency,
     * see {@see CatalogPrice}.
     *
     * @param float|string|null $amount
     * @param string|int|null $scopeCode
     * @return float
     */
    public function toBaseCurrency($amount, $scopeCode = null)
    {
        $rate = (float) $this->storeManager->getStore($scopeCode)->getCurrentCurrencyRate();

        if ($rate <= 0.0) {
            return (float) $amount;
        }

        return (float) $amount / $rate;
    }
}
