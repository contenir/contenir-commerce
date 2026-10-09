<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\Unit\Model\Entity;

use Contenir\Commerce\Exception\OrderNotFoundException;
use Contenir\Commerce\Item\ItemStatus;
use Contenir\Commerce\Model\Entity\ItemEntity;
use Contenir\Commerce\Model\Entity\ItemVariantEntity;
use Contenir\Commerce\Model\Entity\OrderEntity;
use Contenir\Commerce\Model\Entity\OrderItemEntity;
use Contenir\Commerce\Tests\TestAsset\Factory\CommerceFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class EntityAccessorsTest extends TestCase
{
    /**
     * @return array<string, array{ItemStatus, bool}>
     */
    public static function listingProvider(): array
    {
        return [
            'listed'   => [ItemStatus::Listed, true],
            'unlisted' => [ItemStatus::Unlisted, false],
        ];
    }

    /**
     * @return array<string, array{?int, int, bool}>
     */
    public static function stockProvider(): array
    {
        return [
            'untracked, any quantity' => [null, 1_000, true],
            'exactly enough'          => [3, 3, true],
            'more than enough'        => [3, 1, true],
            'one short'               => [3, 4, false],
            'sold out'                => [0, 1, false],
        ];
    }

    #[DataProvider('listingProvider')]
    #[Test]
    public function anItemIsListedOnlyWhileItsStatusSaysSo(ItemStatus $status, bool $listed): void
    {
        static::assertSame($listed, CommerceFactory::item(status: $status)->isListed());
    }

    #[Test]
    public function anItemsTitleIsItsTitleColumn(): void
    {
        static::assertSame([null, 'Rip Tide'], [
            CommerceFactory::item(null)->getTitle(),
            CommerceFactory::item('Rip Tide')->getTitle(),
        ]);
    }

    #[Test]
    public function anOrderExposesItsTotalsAsMoney(): void
    {
        $order            = new OrderEntity();
        $order->total     = 283_000;
        $order->gstAmount = 25_727;

        static::assertSame([283_000, 25_727], [$order->getTotal()->amount, $order->getGstAmount()->amount]);
    }

    #[Test]
    public function anOrderItemPricesItsUnitsAsMoney(): void
    {
        $line            = new OrderItemEntity();
        $line->unitPrice = 3_500;
        $line->quantity  = 3;

        static::assertSame([3_500, 10_500], [$line->getUnitPrice()->amount, $line->getTotal()->amount]);
    }

    #[Test]
    public function anUnsavedOrderHasNoId(): void
    {
        $this->expectException(OrderNotFoundException::class);
        $this->expectExceptionMessage('The order has no id; save it first');

        (new OrderEntity())->getId();
    }

    #[Test]
    public function aSavedOrderReturnsItsId(): void
    {
        $order          = new OrderEntity();
        $order->orderId = 7;

        static::assertSame(7, $order->getId());
    }

    #[Test]
    public function aVariantHasStockForOneUnitByDefault(): void
    {
        static::assertSame([true, false], [
            CommerceFactory::variant(1, stock: 1)->hasStock(),
            CommerceFactory::variant(1, stock: 0)->hasStock(),
        ]);
    }

    #[DataProvider('stockProvider')]
    #[Test]
    public function aVariantHasStockWhileEnoughUnitsRemain(?int $stock, int $quantity, bool $hasStock): void
    {
        static::assertSame($hasStock, CommerceFactory::variant(1, stock: $stock)->hasStock($quantity));
    }

    #[Test]
    public function aVariantPriceIsMoney(): void
    {
        static::assertSame(98_000, CommerceFactory::variant(1, price: 98_000)->getPrice()->amount);
    }

    #[Test]
    public function aVariantTracksStockOnlyWhenItHasACount(): void
    {
        static::assertSame([true, true, false], [
            CommerceFactory::variant(1, stock: 5)->isStockTracked(),
            CommerceFactory::variant(1, stock: 0)->isStockTracked(),
            CommerceFactory::variant(1, stock: null)->isStockTracked(),
        ]);
    }

    #[Test]
    public function newEntitiesTakeTheColumnDefaults(): void
    {
        $order   = new OrderEntity();
        $item    = new ItemEntity();
        $variant = new ItemVariantEntity();
        $line    = new OrderItemEntity();

        static::assertSame(
            ['', 'pending', 0, 0, null, 'listed', null, null, 0, null, 0, 0, 1],
            [
                $order->orderRef,
                $order->status->value,
                $order->total,
                $order->gstAmount,
                $item->title,
                $item->status->value,
                $variant->label,
                $variant->sku,
                $variant->price,
                $variant->stock,
                $variant->sequence,
                $line->unitPrice,
                $line->quantity,
            ],
        );
    }
}
