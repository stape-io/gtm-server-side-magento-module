<?php

namespace Stape\Gtm\Model\Price;

use Magento\Catalog\Pricing\Price\FinalPrice;

/**
 * Resolves catalog product prices pushed to the data layer.
 *
 * {@see \Magento\Catalog\Model\Product::getFinalPrice()} cannot be used here because
 * its currency depends on the product type: simple products return the base currency
 * price attribute, while configurable products resolve through the pricing layer and
 * therefore return an amount already converted to the display currency.
 *
 * The pricing layer is consistent for every product type and is what the storefront
 * itself renders, so it is used as the single source and normalised to the currency
 * selected by the "Use Display Currency for Amounts" flag, see {@see CurrencyResolver}.
 */
class CatalogPrice
{
    /**
     * @var CurrencyResolver $currencyResolver
     */
    private $currencyResolver;

    /**
     * Define class dependencies
     *
     * @param CurrencyResolver $currencyResolver
     */
    public function __construct(CurrencyResolver $currencyResolver)
    {
        $this->currencyResolver = $currencyResolver;
    }

    /**
     * Retrieve the final price of a catalog product in the reported currency
     *
     * @param \Magento\Catalog\Api\Data\ProductInterface|\Magento\Catalog\Model\Product $product
     * @param string|int|null $scopeCode
     * @return float
     */
    public function forProduct($product, $scopeCode = null)
    {
        $displayPrice = $this->displayPrice($product);

        if ($this->currencyResolver->useDisplayCurrency($scopeCode)) {
            return $displayPrice;
        }

        return $this->currencyResolver->toBaseCurrency($displayPrice, $scopeCode);
    }

    /**
     * Retrieve the final price as rendered by the storefront, always in display currency
     *
     * Falls back to the product final price when the pricing layer is unavailable, which
     * happens for product types without a price info implementation.
     *
     * @param \Magento\Catalog\Api\Data\ProductInterface|\Magento\Catalog\Model\Product $product
     * @return float
     */
    private function displayPrice($product)
    {
        try {
            return (float) $product->getPriceInfo()
                ->getPrice(FinalPrice::PRICE_CODE)
                ->getAmount()
                ->getValue();
            // phpcs:disable
        } catch (\Throwable $e) {
            // phpcs:enable
            return (float) $product->getFinalPrice();
        }
    }
}
