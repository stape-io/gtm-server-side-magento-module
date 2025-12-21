<?php

namespace Stape\Gtm\Model\Data;

use Magento\Framework\Model\AbstractExtensibleModel;

class ItemVariant extends AbstractExtensibleModel implements ItemVariantInterface
{

    /**
     * Define variation SKU
     *
     * @param ?string $sku
     * @return ItemVariantInterface
     */
    public function setSku(?string $sku): ItemVariantInterface
    {
        $this->setData(self::SKU, $sku);
        return $this;
    }

    /**
     * Retrieve variation SKU
     *
     * @return string|null
     */
    public function getSku(): string|null
    {
        return $this->_getData(self::SKU);
    }

    /**
     * Define variation id
     *
     * @param string|int|null $variationId
     * @return ItemVariantInterface
     */
    public function setVariationId(string|int|null $variationId): ItemVariantInterface
    {
        $this->setData(self::VARIATION_ID, $variationId);
        return $this;
    }

    /**
     * Retrieve variation id
     *
     * @return string|int|null
     */
    public function getVariationId(): string|int|null
    {
        return $this->_getData(self::VARIATION_ID);
    }
}
