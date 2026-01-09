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
    public function getSku();

    /**
     * Define variation id
     *
     * @param string|int|null $variationId
     * @return ItemVariantInterface
     */
    public function setVariationId($variationId): ItemVariantInterface;

    /**
     * Retrieve Variation id
     *
     * @return string|int|null
     */
    public function getVariationId();
}
