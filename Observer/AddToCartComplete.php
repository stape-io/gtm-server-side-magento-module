<?php

namespace Stape\Gtm\Observer;

use Magento\Checkout\Model\Session;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Stape\Gtm\Model\ConfigProvider;
use Stape\Gtm\Model\Data\DataProviderInterface;
use Stape\Gtm\Model\Product\CategoryResolver;

class AddToCartComplete implements ObserverInterface
{

    /**
     * @var CategoryResolver $categoryResolver
     */
    private $categoryResolver;

    /**
     * @var Session $checkoutSession
     */
    private $checkoutSession;

    /**
     * @var ConfigProvider $configProvider
     */
    private $configProvider;

    /**
     * @var DataProviderInterface $dataProvider
     */
    private $dataProvider;

    /**
     * @var PriceCurrencyInterface $priceCurrency
     */
    private $priceCurrency;

    /**
     * Define class dependencies
     *
     * @param CategoryResolver $categoryResolver
     * @param Session $checkoutSession
     * @param ConfigProvider $configProvider
     * @param DataProviderInterface $dataProvider
     * @param PriceCurrencyInterface $priceCurrency
     */
    public function __construct(
        CategoryResolver $categoryResolver,
        Session $checkoutSession,
        ConfigProvider $configProvider,
        DataProviderInterface $dataProvider,
        PriceCurrencyInterface $priceCurrency
    ) {
        $this->categoryResolver = $categoryResolver;
        $this->checkoutSession = $checkoutSession;
        $this->configProvider = $configProvider;
        $this->dataProvider = $dataProvider;
        $this->priceCurrency = $priceCurrency;
    }

    /**
     * Execute observer logic
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        if (!$this->configProvider->isActive()) {
            return;
        }

        /** @var \Magento\Catalog\Model\Product $product */
        $product = $observer->getData('product');

        /** @var \Magento\Quote\Model\Quote\Item $quote */
        $quoteItem = $this->checkoutSession->getQuote()->getItemByProduct($product);

        $qty = (int)$observer->getData('request')->getParam('qty');
        if ($qty === 0) {
            $qty = 1;
        }
        $category = $this->categoryResolver->resolve($product);
        $childItem = $quoteItem->getHasChildren() ? current($quoteItem->getChildren()) : null;
        $this->dataProvider->add('add_to_cart', [
            'currency' => $this->checkoutSession->getQuote()->getBaseCurrencyCode(),
            'items' => [
                [
                    'item_name' => $product->getName(),
                    'item_id' => $product->getId(),
                    'item_sku' => $product->getSku(),
                    'item_category' => $category ? $category->getName() : null,
                    'price' => $this->priceCurrency->round($quoteItem->getBasePriceInclTax()),
                    'quantity' => $qty,
                    'variation_id' => $childItem ? $childItem->getProductId() : null,
                    'item_variant' => $childItem ? $childItem->getSku() : null,
                ]
            ]
        ]);
    }
}
