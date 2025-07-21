<?php

namespace Stape\Gtm\Plugin\Checkout\Cart;

use Magento\Checkout\Controller\Cart\Delete;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Psr\Log\LoggerInterface;
use Stape\Gtm\Model\ConfigProvider;
use Stape\Gtm\Model\Data\DataProviderInterface;
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
     * Define class dependencies
     *
     * @param CheckoutSession $checkoutSession
     * @param ConfigProvider $configProvider
     * @param DataProviderInterface $dataProvider
     * @param PriceCurrencyInterface $priceCurrency
     * @param CategoryResolver $categoryResolver
     * @param LoggerInterface $logger
     */
    public function __construct(
        CheckoutSession $checkoutSession,
        ConfigProvider $configProvider,
        DataProviderInterface $dataProvider,
        PriceCurrencyInterface $priceCurrency,
        CategoryResolver $categoryResolver,
        LoggerInterface $logger
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->configProvider = $configProvider;
        $this->dataProvider = $dataProvider;
        $this->priceCurrency = $priceCurrency;
        $this->categoryResolver = $categoryResolver;
        $this->logger = $logger;
    }

    /**
     * Add remove from cart event
     *
     * @param Delete $subject
     * @param callable $proceed
     * @return mixed
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function aroundExecute(Delete $subject, callable $proceed)
    {
        if (!$this->configProvider->isActive()) {
            return $proceed();
        }

        $itemId = (int) $subject->getRequest()->getParam('id');
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
                $this->dataProvider->add('remove_from_cart', [
                    'items' => [
                        [
                            'item_name' => $item->getName(),
                            'item_id' => $item->getProduct()->getId(),
                            'item_sku' => $item->getSku(),
                            'item_category' => $category ? $category->getName() : null,
                            'price' => $this->priceCurrency->round($item->getBasePriceInclTax()),
                            'quantity' => $item->getQty(),
                            'variation_id' => $item->getHasChildren() ? current($item->getChildren())->getProductId() : null
                        ]
                    ]
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->notice(sprintf('Could not track remove_from_cart_stape event. Error: %s', $e->getMessage()));
        }

        return $result;
    }
}
