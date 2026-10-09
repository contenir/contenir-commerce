<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\Integration\Model\Repository;

use Contenir\Commerce\Model\Entity\OrderItemEntity;
use Contenir\Commerce\Model\Repository\OrderItemRepository;
use Contenir\Commerce\Tests\Trait\SqliteDatabaseTrait;
use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;

#[Group('integration')]
#[Group('repository')]
final class OrderItemRepositoryTest extends TestCase
{
    use SqliteDatabaseTrait;

    private OrderItemRepository $repository;

    #[Test]
    public function findsTheLinesOfOneOrderInTheOrderTheyWereAdded(): void
    {
        $this->insert('commerce_order_item', ['order_id' => 1, 'title' => 'Zebra Finch', 'unit_price' => 100]);
        $this->insert('commerce_order_item', ['order_id' => 2, 'title' => 'Elsewhere', 'unit_price' => 100]);
        $this->insert('commerce_order_item', ['order_id' => 1, 'title' => 'Apple Gum', 'unit_price' => 200]);

        static::assertSame(
            ['Zebra Finch', 'Apple Gum'],
            array_map(
                static fn(OrderItemEntity $line): string => $line->title,
                $this->repository->findByOrderId(1),
            ),
        );
    }

    #[Test]
    public function mapsEveryColumn(): void
    {
        $this->insert('commerce_order_item', [
            'order_item_id'   => 4,
            'order_id'        => 1,
            'item_id'         => 8,
            'item_variant_id' => 12,
            'title'           => 'Coastal Dawn',
            'variant_label'   => 'A2',
            'description'     => 'June Hollis',
            'unit_price'      => 18_500,
            'quantity'        => 2,
            'created'         => '2026-08-20 10:00:00',
        ]);

        $line = $this->repository->find(4);

        static::assertEquals(
            [4, 1, 8, 12, 'Coastal Dawn', 'A2', 'June Hollis', 18_500, 2, new DateTimeImmutable('2026-08-20 10:00:00')],
            [
                $line?->orderItemId,
                $line?->orderId,
                $line?->itemId,
                $line?->itemVariantId,
                $line?->title,
                $line?->variantLabel,
                $line?->description,
                $line?->unitPrice,
                $line?->quantity,
                $line?->created,
            ],
        );
    }

    #[Override]
    protected function setUp(): void
    {
        $this->setUpDatabase();
        $this->repository = new OrderItemRepository($this->em);
    }
}
