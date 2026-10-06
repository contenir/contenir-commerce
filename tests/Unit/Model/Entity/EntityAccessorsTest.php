<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\Unit\Model\Entity;

use Contenir\Commerce\Artwork\ArtworkStatus;
use Contenir\Commerce\Exception\OrderNotFoundException;
use Contenir\Commerce\Model\Entity\ArtworkEntity;
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
     * @return array<string, array{ArtworkStatus, bool}>
     */
    public static function availabilityProvider(): array
    {
        return [
            'available' => [ArtworkStatus::Available, true],
            'sold'      => [ArtworkStatus::Sold, false],
        ];
    }

    #[DataProvider('availabilityProvider')]
    #[Test]
    public function anArtworkIsAvailableOnlyWhileUnsold(ArtworkStatus $status, bool $available): void
    {
        static::assertSame($available, CommerceFactory::artwork(status: $status)->isAvailable());
    }

    #[Test]
    public function anArtworkPriceIsMoney(): void
    {
        static::assertSame(98_000, CommerceFactory::artwork(price: 98_000)->getPrice()->amount);
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
    public function anOrderItemPriceIsMoney(): void
    {
        $line        = new OrderItemEntity();
        $line->price = 3_500;

        static::assertSame(3_500, $line->getPrice()->amount);
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
    public function newEntitiesTakeTheColumnDefaults(): void
    {
        $order   = new OrderEntity();
        $artwork = new ArtworkEntity();

        static::assertSame(
            ['', 'pending', 0, 0, 'artwork', 'available', 0],
            [
                $order->orderRef,
                $order->status->value,
                $order->total,
                $order->gstAmount,
                $artwork->itemType->value,
                $artwork->status->value,
                $artwork->price,
            ],
        );
    }

    #[Test]
    public function theDefaultArtworkHasNoTitleOfItsOwn(): void
    {
        static::assertNull(CommerceFactory::artwork()->getTitle());
    }
}
