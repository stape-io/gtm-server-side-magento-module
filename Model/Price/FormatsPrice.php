<?php

namespace Stape\Gtm\Model\Price;

use Magento\Framework\Pricing\PriceCurrencyInterface;

/**
 * Formats monetary values as canonical fixed-precision strings for the dataLayer.
 *
 * Produces a locale-independent string with a "." decimal separator, no thousands
 * grouping and a fixed 2 decimals (e.g. 10 => "10.00", 1234.5 => "1234.50"), so GTM/GA4
 * always receives consistent price/value formatting regardless of the store locale.
 *
 * Consuming classes must expose a {@see PriceCurrencyInterface} as $this->priceCurrency.
 */
trait FormatsPrice
{
    /**
     * Format a monetary amount as a canonical fixed-precision string.
     *
     * @param float|int|string|null $amount
     * @return string
     */
    protected function formatPrice($amount): string
    {
        /** @var PriceCurrencyInterface $priceCurrency */
        $priceCurrency = $this->priceCurrency;

        return number_format((float) $priceCurrency->round((float) $amount), 2, '.', '');
    }
}
