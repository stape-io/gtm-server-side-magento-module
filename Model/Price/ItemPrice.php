<?php

namespace Stape\Gtm\Model\Price;

use Stape\Gtm\Model\ConfigProvider;

/**
 * Resolves item prices pushed to the data layer.
 *
 * Two independent configuration flags govern the result:
 *
 * - "Exclude Tax from Item price" picks between the including and excluding tax value.
 *   Disabled by default, so prices include tax.
 * - "Use Display Currency for Amounts" picks between the quote/order currency and the
 *   website base currency. Disabled by default, so prices use the base currency,
 *   see {@see CurrencyResolver}.
 *
 * The tax flag governs per unit item prices and item line totals only, never order
 * level totals such as grand total, tax or shipping.
 *
 * Whichever currency is selected, all four combinations resolve within that one
 * currency, so a value can never be reported under the wrong currency code.
 */
class ItemPrice
{
    /**
     * @var ConfigProvider $configProvider
     */
    private $configProvider;

    /**
     * @var CurrencyResolver $currencyResolver
     */
    private $currencyResolver;

    /**
     * Define class dependencies
     *
     * @param ConfigProvider $configProvider
     * @param CurrencyResolver $currencyResolver
     */
    public function __construct(ConfigProvider $configProvider, CurrencyResolver $currencyResolver)
    {
        $this->configProvider = $configProvider;
        $this->currencyResolver = $currencyResolver;
    }

    /**
     * Retrieve unit price of a quote item
     *
     * Quote item "price" holds the base currency amount, "converted_price" the quote
     * currency one.
     *
     * @param \Magento\Quote\Model\Quote\Item\AbstractItem $item
     * @param string|int|null $scopeCode
     * @return float
     */
    public function forQuoteItem($item, $scopeCode = null)
    {
        if ($this->currencyResolver->useDisplayCurrency($scopeCode)) {
            return $this->resolve($item->getConvertedPrice(), $item->getPriceInclTax(), $scopeCode);
        }

        return $this->resolve($item->getPrice(), $item->getBasePriceInclTax(), $scopeCode);
    }

    /**
     * Retrieve line total of a quote item
     *
     * @param \Magento\Quote\Model\Quote\Item\AbstractItem $item
     * @param string|int|null $scopeCode
     * @return float
     */
    public function rowTotalForQuoteItem($item, $scopeCode = null)
    {
        if ($this->currencyResolver->useDisplayCurrency($scopeCode)) {
            return $this->resolve($item->getRowTotal(), $item->getRowTotalInclTax(), $scopeCode);
        }

        return $this->resolve($item->getBaseRowTotal(), $item->getBaseRowTotalInclTax(), $scopeCode);
    }

    /**
     * Retrieve unit price of an order, invoice or credit memo item
     *
     * Sales item "price" is converted from the quote item calculation price, so it is
     * already in order currency, while "base_price" holds the base currency amount.
     *
     * @param \Magento\Sales\Api\Data\OrderItemInterface|\Magento\Framework\DataObject $item
     * @param string|int|null $scopeCode
     * @return float
     */
    public function forSalesItem($item, $scopeCode = null)
    {
        if ($this->currencyResolver->useDisplayCurrency($scopeCode)) {
            return $this->resolve($item->getPrice(), $item->getPriceInclTax(), $scopeCode);
        }

        return $this->resolve($item->getBasePrice(), $item->getBasePriceInclTax(), $scopeCode);
    }

    /**
     * Pick the configured price
     *
     * Falls back to the excluding tax value when the including tax one has not been
     * calculated yet, which happens on quotes and orders created without a tax
     * collection pass. Both arguments are always in the same currency, so the
     * fallback can never switch currency.
     *
     * @param float|string|null $excludingTax
     * @param float|string|null $includingTax
     * @param string|int|null $scopeCode
     * @return float
     */
    private function resolve($excludingTax, $includingTax, $scopeCode = null)
    {
        if ($this->isTaxExcluded($scopeCode) || $includingTax === null) {
            return (float) $excludingTax;
        }

        return (float) $includingTax;
    }

    /**
     * Check if tax has to be excluded from item prices
     *
     * @param string|int|null $scopeCode
     * @return bool
     */
    private function isTaxExcluded($scopeCode = null)
    {
        return $this->configProvider->isItemPriceTaxExcluded($scopeCode);
    }
}
