<?php

namespace Stape\Gtm\Test\Unit\Model\Data;

use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Creditmemo\Item as CreditmemoItem;
use Magento\Sales\Model\Order\Item as OrderItem;
use PHPUnit\Framework\TestCase;
use Stape\Gtm\Model\Data\Converter;
use Stape\Gtm\Model\Data\ItemVariantFactory;
use Stape\Gtm\Model\Data\ItemVariantInterface;
use Stape\Gtm\Model\Data\Order as OrderData;
use Stape\Gtm\Model\Price\CurrencyResolver;
use Stape\Gtm\Model\Price\ItemPrice;
use Stape\Gtm\Model\Price\Totals;
use Stape\Gtm\Model\Product\CategoryResolver;

class ConverterTest extends TestCase
{
    public function testPrepareCreditMemoItemsWithDeletedProduct()
    {
        $orderItem = $this->createStub(OrderItem::class);
        $orderItem->method('getParentItemId')->willReturn(null);
        $orderItem->method('getProduct')->willReturn(null);
        $orderItem->method('getSku')->willReturn('SKU-123');
        $orderItem->method('getStoreId')->willReturn(1);

        $creditmemoItem = $this->createStub(CreditmemoItem::class);
        $creditmemoItem->method('getOrderItem')->willReturn($orderItem);
        $creditmemoItem->method('getProductId')->willReturn(10);
        $creditmemoItem->method('getName')->willReturn('Deleted Product');
        $creditmemoItem->method('getQty')->willReturn(1.0);

        $creditmemo = $this->createStub(Creditmemo::class);
        $creditmemo->method('getItems')->willReturn([$creditmemoItem]);

        $this->assertEquals(
            [[
                'item_id' => 10,
                'item_name' => 'Deleted Product',
                'item_sku' => 'SKU-123',
                'item_category' => '',
                'price' => '10.00',
                'quantity' => 1.0,
                'item_variant' => null,
                'variation_id' => null,
            ]],
            $this->createConverter()->prepareCreditMemoItems($creditmemo)
        );
    }

    public function testPrepareOrderItemsWithDeletedProduct()
    {
        $orderItem = $this->createStub(OrderItem::class);
        $orderItem->method('getProduct')->willReturn(null);
        $orderItem->method('getProductId')->willReturn(10);
        $orderItem->method('getName')->willReturn('Deleted Product');
        $orderItem->method('getSku')->willReturn('SKU-123');
        $orderItem->method('getStoreId')->willReturn(1);
        $orderItem->method('getQtyOrdered')->willReturn(2.0);

        $order = $this->createStub(Order::class);
        $order->method('getAllVisibleItems')->willReturn([$orderItem]);

        $this->assertEquals(
            [[
                'item_id' => 10,
                'item_name' => 'Deleted Product',
                'item_sku' => 'SKU-123',
                'item_category' => '',
                'price' => '10.00',
                'quantity' => 2.0,
                'item_variant' => null,
                'variation_id' => null,
                'purchase_type' => false,
            ]],
            $this->createConverter()->prepareOrderItems($order)
        );
    }

    private function createConverter(): Converter
    {
        $itemVariant = $this->createStub(ItemVariantInterface::class);
        $itemVariant->method('getSku')->willReturn(null);
        $itemVariant->method('getVariationId')->willReturn(null);

        $itemVariantFactory = $this->createStub(ItemVariantFactory::class);
        $itemVariantFactory->method('createFromOrderItem')->willReturn($itemVariant);

        $priceCurrency = $this->createStub(PriceCurrencyInterface::class);
        $priceCurrency->method('round')->willReturnArgument(0);

        $itemPrice = $this->createStub(ItemPrice::class);
        $itemPrice->method('forSalesItem')->willReturn(10.0);

        return new Converter(
            $this->createStub(CategoryResolver::class),
            $priceCurrency,
            $this->createStub(OrderData::class),
            $itemVariantFactory,
            $itemPrice,
            $this->createStub(Totals::class),
            $this->createStub(CurrencyResolver::class)
        );
    }
}
