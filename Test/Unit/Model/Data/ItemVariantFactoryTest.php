<?php

namespace Stape\Gtm\Test\Unit\Model\Data;

use Magento\Sales\Model\Order\Item as OrderItem;
use PHPUnit\Framework\TestCase;
use Stape\Gtm\Model\Data\ItemVariantFactory;

class ItemVariantFactoryTest extends TestCase
{
    public function testResolveVariantSkuWithDeletedProduct()
    {
        $orderItem = $this->createStub(OrderItem::class);
        $orderItem->method('getProduct')->willReturn(null);

        $factory = new class extends ItemVariantFactory {
            public function __construct()
            {
            }

            public function resolveSku($item)
            {
                return $this->resolveVariantSku($item);
            }
        };

        $this->assertNull($factory->resolveSku($orderItem));
    }
}
