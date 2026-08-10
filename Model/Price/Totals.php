<?php

namespace Stape\Gtm\Model\Price;

/**
 * Resolves entity level totals to push to the data layer.
 *
 * Quotes, orders and credit memos all store every total twice, once in the entity
 * currency and once in the website base currency under a "base_" prefixed field, so a
 * single lookup covers all three entity types.
 *
 * Which of the two is returned follows the "Use Display Currency for Amounts" flag,
 * see {@see CurrencyResolver}.
 */
class Totals
{
    /**
     * Total fields this class is expected to resolve
     */
    public const FIELD_GRAND_TOTAL = 'grand_total';
    public const FIELD_SUBTOTAL = 'subtotal';
    public const FIELD_TAX_AMOUNT = 'tax_amount';
    public const FIELD_SHIPPING_AMOUNT = 'shipping_amount';
    public const FIELD_DISCOUNT_AMOUNT = 'discount_amount';

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
     * Retrieve a total of a quote, order or credit memo in the reported currency
     *
     * @param \Magento\Framework\DataObject $entity
     * @param string $field
     * @param string|int|null $scopeCode
     * @return float
     */
    public function forEntity($entity, $field, $scopeCode = null)
    {
        if (!$this->currencyResolver->useDisplayCurrency($scopeCode)) {
            $field = 'base_' . $field;
        }

        return (float) $entity->getData($field);
    }
}
