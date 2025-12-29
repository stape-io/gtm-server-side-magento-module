<?php

namespace Stape\Gtm\Model\Data;

interface ItemVariantInterface
{
    /*
     * Variatn SKU
     */
    public const SKU = 'sku';

    /*
     * Variation ID
     */
    public const VARIATION_ID = 'variation_id';

    /**
     * Define variation SKU
     *
     * @param string|null $sku
     * @return ItemVariantInterface
     */
    public function setSku(?string $sku): ItemVariantInterface;

    /**
     * Retrieve variation SKU
     *
     * @return string|null
     */
    public function getSku(): string|null;

    /**
     * Define variation id
     *
     * @param string|int $variationId
     * @return ItemVariantInterface
     */
    public function setVariationId(string|int|null $variationId): ItemVariantInterface;

    /**
     * Retrieve Variation id
     *
     * @return string|int|null
     */
    public function getVariationId(): string|int|null;
}
