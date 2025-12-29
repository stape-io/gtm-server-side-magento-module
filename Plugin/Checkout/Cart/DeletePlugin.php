<?php

namespace Stape\Gtm\Plugin\Checkout\Cart;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Checkout\Controller\Cart\Delete;
use Magento\Checkout\Controller\Sidebar\RemoveItem;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Psr\Log\LoggerInterface;
use Stape\Gtm\Model\ConfigProvider;
use Stape\Gtm\Model\Data\DataProviderInterface;
use Stape\Gtm\Model\Data\ItemVariantFactory;
use Stape\Gtm\Model\Datalayer\Modifier\CartState;
use Stape\Gtm\Model\Product\CategoryResolver;

class DeletePlugin
{
    /**
     * @var CheckoutSession $checkoutSession
     */
    protected $checkoutSession;

    /**
     * @var ConfigProvider $configProvider
     */
    protected $configProvider;

    /**
     * @var DataProviderInterface $dataProvider
     */
    protected $dataProvider;

    /**
     * @var PriceCurrencyInterface $priceCurrency
     */
    protected $priceCurrency;

    /**
     * @var CategoryResolver $categoryResolver
     */
    protected $categoryResolver;

    /**
     * @var LoggerInterface $logger
     */
    protected $logger;

    /**
     * @var CartState $cartStateModifier
     */
    protected $cartStateModifier;

    /**
     * @var ItemVariantFactory $itemVariantFactory
     */
    protected $itemVariantFactory;

    /**
     * Define class dependencies
     *
     * @param CheckoutSession $checkoutSession
     * @param ConfigProvider $configProvider
     * @param DataProviderInterface $dataProvider
     * @param PriceCurrencyInterface $priceCurrency
     * @param CategoryResolver $categoryResolver
     * @param LoggerInterface $logger
     * @param CartState $cartStateModifier
     * @param ItemVariantFactory $itemVariantFactory
     */
    public function __construct(
        CheckoutSession $checkoutSession,
        ConfigProvider $configProvider,
        DataProviderInterface $dataProvider,
        PriceCurrencyInterface $priceCurrency,
        CategoryResolver $categoryResolver,
        LoggerInterface $logger,
        CartState $cartStateModifier,
        ItemVariantFactory $itemVariantFactory
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->configProvider = $configProvider;
        $this->dataProvider = $dataProvider;
        $this->priceCurrency = $priceCurrency;
        $this->categoryResolver = $categoryResolver;
        $this->logger = $logger;
        $this->cartStateModifier = $cartStateModifier;
        $this->itemVariantFactory = $itemVariantFactory;
    }

    /**
     * Add remove from cart event
     *
     * @param Delete|RemoveItem $subject
     * @param callable $proceed
     * @return mixed
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function aroundExecute($subject, callable $proceed)
    {
        if (!$this->configProvider->isActive()) {
            return $proceed();
        }

        $request = $subject->getRequest();
        $itemId = (int) ($request->getParam('id') ?: $request->getParam('item_id'));

        $quote = $this->checkoutSession->getQuote();
        /** @var \Magento\Quote\Model\Quote\Item $item */
        if (!$item = $quote->getItemById($itemId)) {
            return $proceed();
        }

        try {
            $product = $item->getProduct();
            $category = $this->categoryResolver->resolve($product);
            $result = $proceed();

            if ($item->isDeleted()) {
                $itemVariant = $this->itemVariantFactory->createFromQuoteItem($item);
                $eventData = $this->cartStateModifier->modifyEventData([
                    'value' => $this->priceCurrency->round($item->getBasePriceInclTax()),
                    'items' => [
                        [
                            'item_name' => $item->getName(),
                            'item_id' => $item->getProduct()->getId(),
                            'item_sku' => $item->getProduct()->getData(ProductInterface::SKU),
                            'item_category' => $category ? $category->getName() : null,
                            'price' => $this->priceCurrency->round($item->getBasePriceInclTax()),
                            'quantity' => $item->getQty(),
                            'variation_id' => $itemVariant->getVariationId(),
                            'item_variant' => $itemVariant->getSku(),
                        ]
                    ]
                ]);
                $this->dataProvider->add('remove_from_cart', $eventData);
            }
        } catch (\Exception $e) {
            $this->logger->notice(sprintf('Could not track remove_from_cart_stape event. Error: %s', $e->getMessage()));
        }

        return $result;
    }
}
