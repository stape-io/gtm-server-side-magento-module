<?php

namespace Stape\Gtm\ViewModel;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Checkout\Model\Session;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Stape\Gtm\Model\Data\ItemVariantFactory;
use Stape\Gtm\Model\Datalayer\Modifier\PoolInterface;
use Stape\Gtm\Model\Product\CategoryResolver;
use Stape\Gtm\Model\Datalayer\Formatter\Event as EventFormatter;
use Stape\Gtm\Model\Price\CurrencyResolver;
use Stape\Gtm\Model\Price\ItemPrice;
use Stape\Gtm\Model\Price\Totals;

class Checkout extends DatalayerAbstract implements ArgumentInterface
{
    /**
     * @var Session $checkoutSession
     */
    private $checkoutSession;

    /**
     * @var CategoryResolver $categoryResolver
     */
    private $categoryResolver;

    /**
     * @var ItemVariantFactory $itemVariantFactory
     */
    protected $itemVariantFactory;

    /**
     * @var ItemPrice $itemPrice
     */
    protected $itemPrice;

    /**
     * @var Totals $totals
     */
    protected $totals;

    /**
     * Define class dependencies
     *
     * @param Json $json
     * @param StoreManagerInterface $storeManager
     * @param Session $checkoutSession
     * @param PriceCurrencyInterface $priceCurrency
     * @param CategoryResolver $categoryResolver
     * @param EventFormatter $eventFormatter
     * @param PoolInterface $modifierPool
     * @param ItemVariantFactory $itemVariantFactory
     * @param ItemPrice $itemPrice
     * @param Totals $totals
     * @param CurrencyResolver $currencyResolver
     */
    public function __construct(
        Json $json,
        StoreManagerInterface $storeManager,
        Session $checkoutSession,
        PriceCurrencyInterface $priceCurrency,
        CategoryResolver $categoryResolver,
        EventFormatter $eventFormatter,
        PoolInterface $modifierPool,
        ItemVariantFactory $itemVariantFactory,
        ItemPrice $itemPrice,
        Totals $totals,
        CurrencyResolver $currencyResolver
    ) {
        parent::__construct($json, $eventFormatter, $storeManager, $priceCurrency, $currencyResolver, $modifierPool);
        $this->checkoutSession = $checkoutSession;
        $this->categoryResolver = $categoryResolver;
        $this->itemVariantFactory = $itemVariantFactory;
        $this->itemPrice = $itemPrice;
        $this->totals = $totals;
    }

    /**
     * Prepare items
     *
     * @param \Magento\Quote\Model\Quote $quote
     * @return array
     */
    public function prepareItems(\Magento\Quote\Model\Quote $quote)
    {
        $items = [];
        /** @var \Magento\Quote\Model\Quote\Item $item */
        foreach ($quote->getAllVisibleItems() as $item) {
            $category = $this->categoryResolver->resolve($item->getProduct());
            $itemVariant = $this->itemVariantFactory->createFromQuoteItem($item);
            $items[] = [
                'item_name' => $item->getName(),
                'item_id' => $item->getProductId(),
                'item_sku' => $item->getProduct()->getData(ProductInterface::SKU),
                'item_category' => $category ? $category->getName() : null,
                'price' => $this->formatPrice($this->itemPrice->forQuoteItem($item)),
                'quantity' => (int) $item->getQty(),
                'variation_id' => $itemVariant->getVariationId(),
                'item_variant' => $itemVariant->getSku(),
            ];
        }
        return $items;
    }

    /**
     * Retrieve json
     *
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getEventData()
    {
        /** @var \Magento\Quote\Model\Quote $quote */
        if (!$quote = $this->checkoutSession->getQuote()) {
            return [];
        }

        $grandTotal = $this->totals->forEntity($quote, Totals::FIELD_GRAND_TOTAL);

        return [
            'event' => $this->eventFormatter->formatName('begin_checkout'),
            'ecomm_pagetype' => 'basket',
            'cart_quantity' => (int) $quote->getItemsQty(),
            'cart_total' => $this->formatPrice($grandTotal),
            'ecommerce' => [
                'value' => $this->formatPrice($grandTotal),
                'currency' => $this->currencyResolver->codeForQuote($quote),
                'items' => $this->prepareItems($quote),
            ],
        ];
    }
}
