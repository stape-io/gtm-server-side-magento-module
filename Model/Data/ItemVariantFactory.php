<?php

namespace Stape\Gtm\Model\Data;

use Magento\Quote\Model\Quote\Item as QuoteItem;
use Magento\Sales\Model\Order\Item as OrderItem;

class ItemVariantFactory
{

    /** @var ItemVariantInterfaceFactory $factory */
    protected $factory;

    /**
     * Define class dependencies
     *
     * @param ItemVariantInterfaceFactory $factory
     */
    public function __construct(\Stape\Gtm\Model\Data\ItemVariantInterfaceFactory $factory)
    {
        $this->factory = $factory;
    }

    /**
     * Retrieve child
     *
     * @param QuoteItem|OrderItem $item
     * @return false|mixed|null
     */
    protected function getChild($item)
    {
        if (!$item->getHasChildren()) {
            return null;
        }

        if ($item instanceof QuoteItem) {
            return current($item->getChildren());
        }
        return current($item->getChildrenItems());
    }

    /**
     * Resolve item variant SKU
     *
     * @param QuoteItem|OrderItem $item
     * @return string|null
     */
    protected function resolveVariantSku($item)
    {
        $baseSku = $item->getProduct()->getData('sku');
        return ($item->getSku() !== $baseSku && strpos($item->getSku(), $baseSku) === 0)
            ? ltrim(substr($item->getSku(), strlen($baseSku)), '- ')
            : null;
    }

    /**
     * Resolve Variation Id
     *
     * @param QuoteItem|OrderItem $item
     * @return int|null
     */
    protected function resolveVariationId($item)
    {
        $childItem = $item->getHasChildren() ? current($item->getChildrenItems() ?? []) : null;
        return $childItem ? $childItem->getProductId() : null;
    }

    /**
     * Create from Item Variant from Quote item
     *
     * @param QuoteItem $quoteItem
     * @return ItemVariantInterface
     */
    public function createFromQuoteItem(QuoteItem $quoteItem): ItemVariantInterface
    {
        $itemVariant = $this->factory->create();

        if ($child = $this->getChild($quoteItem)) {
            $itemVariant->setSku($child->getSku())
                ->setVariationId($child->getProductId());
            return $itemVariant;
        }

        $itemVariant->setVariationId($this->resolveVariationId($quoteItem))
            ->setSku($this->resolveVariantSku($quoteItem));

        return $itemVariant;
    }

    /**
     * Create Item Variant from Order Item
     *
     * @param OrderItem $orderItem
     * @return ItemVariantInterface
     */
    public function createFromOrderItem(OrderItem $orderItem): ItemVariantInterface
    {
        $itemVariant = $this->factory->create();

        if ($child = $this->getChild($orderItem)) {
            $itemVariant->setSku($child->getSku())
                ->setVariationId($child->getProductId());
            return $itemVariant;
        }

        $itemVariant->setVariationId($this->resolveVariationId($orderItem))
            ->setSku($this->resolveVariantSku($orderItem));
        return $itemVariant;
    }
}
